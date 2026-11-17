<?php

use App\Livewire\EditPatient;
use App\Livewire\PatientInformationForm;
use App\Models\PatientInformation;
use App\Models\User;
use Livewire\Livewire;

it('rejects missing or invalid contact details before reviewing or saving', function (string $field, mixed $value, string $rule, string $component) {
    $patient = $component === EditPatient::class ? PatientInformation::factory()->create() : null;
    $originalAttributes = $patient?->fresh()->getAttributes();
    $form = Livewire::actingAs(User::factory()->create())
        ->test($component, $patient === null ? [] : ['patient' => $patient])
        ->set('first_name', 'Maria')->set('last_name', 'Santos')->set('birthdate', '1995-02-14')
        ->set('address', 'Alabang, Muntinlupa City')
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set($field, $value)->call('reviewDetails')->assertHasErrors([$field => $rule])
        ->assertSet('showReview', false)->assertSet('first_name', 'Maria');

    if ($patient === null) {
        $form->call('continueRegistration')->assertHasErrors([$field => $rule])->assertSet('registrationStep', 1);
    }
    $form->call($patient === null ? 'savePatient' : 'saveChanges')->assertHasErrors([$field => $rule])
        ->assertSee('id="patient-'.$field.'-error"', false)
        ->assertNotDispatched($patient === null ? 'patient-information-saved' : 'patient-updated');

    expect(PatientInformation::count())->toBe($patient === null ? 0 : 1);
    if ($patient !== null) {
        expect($patient->fresh()->getAttributes())->toBe($originalAttributes);
    }
})->with([
    'missing mobile' => ['contact_number', '', 'required'],
    'null mobile' => ['contact_number', null, 'required'],
    'blank mobile' => ['contact_number', '   ', 'required'],
    'malformed mobile' => ['contact_number', 'invalid-number', 'regex'],
    'mobile too long' => ['contact_number', str_repeat('1', 21), 'max'],
    'missing emergency contact' => ['emergency_contact', '', 'required'],
    'null emergency contact' => ['emergency_contact', null, 'required'],
    'blank emergency contact' => ['emergency_contact', '   ', 'required'],
    'emergency contact too long' => ['emergency_contact', str_repeat('a', 256), 'max'],
])->with([PatientInformationForm::class, EditPatient::class]);

it('saves the required contact details on registration and editing', function (string $component) {
    $patient = $component === EditPatient::class ? PatientInformation::factory()->create() : null;
    $form = Livewire::actingAs(User::factory()->create())
        ->test($component, $patient === null ? [] : ['patient' => $patient])
        ->set('first_name', 'Maria')->set('last_name', 'Santos')->set('birthdate', '1995-02-14')
        ->set('address', 'Alabang, Muntinlupa City')
        ->set('contact_number', '+63 (917) 123-4567')
        ->set('emergency_contact', 'Jose Santos, spouse, 09171234568')
        ->call('reviewDetails')->assertHasNoErrors()->assertSet('showReview', true)
        ->assertSee('+63 (917) 123-4567')->assertSee('Jose Santos, spouse, 09171234568')
        ->call($patient === null ? 'savePatient' : 'saveChanges')->assertHasNoErrors();

    $saved = $patient?->fresh() ?? PatientInformation::findOrFail($form->get('registeredPatientId'));
    expect($saved->contact_number)->toBe('+63 (917) 123-4567')
        ->and($saved->emergency_contact)->toBe('Jose Santos, spouse, 09171234568');
})->with([PatientInformationForm::class, EditPatient::class]);
