<?php

use App\Livewire\EditPatient;
use App\Livewire\PatientInformationForm;
use App\Livewire\PatientProfileForm;
use App\Livewire\PatientTable;
use App\Livewire\PatientVitalSignForm;
use App\Livewire\VitalSignsForm;
use App\Models\PatientInformation;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\PatientCategories;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-16 23:59:59 Asia/Manila'));
});

it('reads current age from birthdate on every access without changing legacy stored values', function () {
    $patient = PatientInformation::factory()->create(['birthdate' => '2006-11-17', 'patient_category' => 'Adolescent']);
    DB::table('patient_information')->where('patient_id', $patient->patient_id)->update(['age' => 99]);
    $patient->refresh();
    $attributes = $patient->getAttributes();

    expect($patient->age)->toBe(19)->and($patient->toArray()['age'])->toBe(19)
        ->and($patient->current_category)->toBe('Adolescent');
    $this->travel(1)->seconds();
    expect($patient->age)->toBe(20)->and($patient->toArray()['age'])->toBe(20)
        ->and($patient->current_category)->toBe('Adult')
        ->and($patient->fresh()->getAttributes())->toBe($attributes);
});

it('does not use stored ages when a birthdate is missing invalid or in the future', function (mixed $birthdate) {
    $patient = PatientInformation::factory()->make(['birthdate' => $birthdate]);
    expect($patient->age)->toBeNull()->and($patient->current_category)->toBeNull();
})->with([null, '', 'not-a-date', '2026-02-30', '2026-11-17']);

it('saves birthdates without persisting calculated ages in registration and editing', function (string $component) {
    $patient = $component === EditPatient::class ? PatientInformation::factory()->create() : null;
    $form = Livewire::actingAs(User::factory()->create())
        ->test($component, $patient === null ? [] : ['patient' => $patient])
        ->set('first_name', 'Birthday')->set('last_name', 'Patient')->set('birthdate', '2006-11-17')
        ->set('address', 'Alabang')->set('contact_number', '09171234567')
        ->set('emergency_contact', 'Maria Santos, relative, 09171234568')
        ->assertSet('age', 19)->assertDontSee('wire:model="age"', false)->assertDontSee('wire:model.defer="age"', false)
        ->call($patient === null ? 'savePatient' : 'saveChanges')->assertHasNoErrors();
    $saved = $patient?->fresh() ?? PatientInformation::findOrFail($form->get('registeredPatientId'));
    expect($saved->getRawOriginal('age'))->toBeNull()->and($saved->birthdate)->toBe('2006-11-17')->and($saved->age)->toBe(19);
    $this->travel(1)->seconds();
    expect($saved->age)->toBe(20)->and($saved->getRawOriginal('age'))->toBeNull();
})->with([PatientInformationForm::class, EditPatient::class, PatientVitalSignForm::class]);

it('refreshes patient records and clinical age assessments on a birthday', function () {
    $patient = PatientInformation::factory()->create(['birthdate' => '2006-11-17']);
    $staff = User::factory()->create();
    $table = Livewire::actingAs($staff)->test(PatientTable::class)->call('viewPatient', $patient->patient_id);
    expect($table->get('selectedPatient')->age)->toBe(19);
    $vitals = Livewire::actingAs($staff)->test(VitalSignsForm::class, ['patient_id' => $patient->patient_id])
        ->set('height', 160)->set('weight', 56.32)->assertSet('bmiCategory', 'Age-specific assessment required');

    $this->travel(1)->seconds();
    $table->call('$refresh');
    expect($table->get('selectedPatient')->age)->toBe(20);
    $vitals->call('$refresh')->assertSet('bmiCategory', 'Normal');
    expect($patient->fresh()->getRawOriginal('age'))->toBeNull();
});

it('changes existing profile categories on birthdays without editing patient records or losing history', function (string $birthdate, string $oldCategory, string $newCategory, string $field) {
    $patient = PatientInformation::factory()->create(['birthdate' => $birthdate, 'patient_category' => $oldCategory]);
    PatientProfile::factory()->create(['patient_id' => $patient->patient_id, 'nutrition_status' => 'Historical child assessment']);
    $attributes = $patient->fresh()->getAttributes();
    $profile = Livewire::actingAs(User::factory()->create(['role' => 'Admin']))
        ->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->assertSet('patientCategory', $oldCategory)->call('editProfile')->set('profile.birthplace', 'Unsaved birthplace');

    $this->travel(1)->seconds();
    $profile->call('$refresh')->assertSet('patientCategory', $newCategory)
        ->assertSet('profile.birthplace', 'Unsaved birthplace')->assertSee('id="profile-'.$field.'"', false)
        ->assertDontSee('id="profile-nutrition_status"', false);
    expect($patient->fresh()->getAttributes())->toBe($attributes);
    $profile->call('saveProfile')->assertHasNoErrors();
    expect($patient->registrationProfile->nutrition_status)->toBe('Historical child assessment')
        ->and($patient->fresh()->birthdate)->toBe($birthdate)
        ->and($patient->fresh()->getRawOriginal('age'))->toBeNull();
})->with([
    ['2016-11-17', 'Child', 'Adolescent', 'headss_home'],
    ['2006-11-17', 'Adolescent', 'Adult', 'smoking'],
]);

it('keeps historical age at visit separate from the current age using the same calculator', function () {
    $patient = PatientInformation::factory()->create(['birthdate' => '2006-11-17']);
    expect($patient->age)->toBe(19)
        ->and(app(PatientCategories::class)->age($patient->birthdate, new DateTimeImmutable('2020-11-16 Asia/Manila')))->toBe(13);
});
