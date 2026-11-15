<?php

use App\Livewire\EditPatient;
use App\Livewire\PatientInformationForm;
use App\Livewire\PatientProfileForm;
use App\Models\Household;
use App\Models\ImmunizationDose;
use App\Models\PatientCondition;
use App\Models\PatientInformation;
use App\Models\PatientProfile;
use App\Models\PatientProfileAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function newRegistrationForProfile(?User $user = null): Testable
{
    return Livewire::actingAs($user ?? User::factory()->create())->test(PatientInformationForm::class)
        ->call('selectCategory', 'Adult')
        ->set('first_name', 'Maria')->set('last_name', 'Lopez')->set('birthdate', '1995-02-14')->set('address', 'Alabang, Muntinlupa City');
}

function documentedDose(array $overrides = []): array
{
    return [...['vaccine_name' => 'IPV', 'schedule' => 'Other', 'other_schedule' => 'Documented schedule', 'dose' => 'Dose 1', 'status' => 'Completed', 'administered_at' => now('Asia/Manila')->toDateString(), 'administered_by' => 'Documented vaccinator', 'remarks' => 'From patient vaccination card'], ...$overrides];
}

it('registers an individual with optional information and a generated family number', function () {
    $staff = User::factory()->create();
    $form = newRegistrationForProfile($staff)->set('householdMode', 'new')
        ->set('householdData', ['label' => 'Lopez Family', 'address' => 'Shared address', 'family_count' => 2, 'average_income' => '12500.50', 'water_source' => 'Communal Faucet'])
        ->set('householdRelationship', 'Mother')->set('profile.civil_status', 'Married')
        ->set('profile.four_ps', 'Yes')->set('profile.four_ps_number', '4PS-1')->set('profile.preparer_designation', 'Health worker')
        ->call('reviewDetails')->assertHasNoErrors()->assertSet('showReview', true)
        ->call('savePatient')->assertHasNoErrors()->assertSee('Patient registered successfully.');
    $patient = PatientInformation::sole();
    $household = Household::sole();
    expect($household->family_number)->toBe('FN-0001')->and($household->family_count)->toBe(2)
        ->and($patient->household_id)->toBe($household->id)->and($patient->household_relationship)->toBe('Mother')
        ->and($patient->registrationProfile->four_ps_number)->toBe('4PS-1')
        ->and($patient->registrationProfile->prepared_by)->toBe($staff->user_id)
        ->and($patient->registrationProfile->completed_at)->not->toBeNull()
        ->and($patient->registrationProfile->smoking)->toBeNull();
    $form->assertSet('profile', [])->assertSet('householdMode', 'none')->assertSet('householdId', null);
});

it('shares households while retaining distinct patient identifiers and medical relationships', function () {
    $household = Household::factory()->create();
    $first = PatientInformation::factory()->create(['household_id' => $household->id]);
    newRegistrationForProfile()->set('householdMode', 'existing')->set('householdSearch', $household->family_number)->assertSee($household->label)
        ->call('selectHousehold', $household->id)->set('householdRelationship', 'Child')
        ->call('savePatient')->assertHasNoErrors();
    $second = PatientInformation::latest('patient_id')->first();
    expect($household->members()->count())->toBe(2)->and($first->patient_id)->not->toBe($second->patient_id)
        ->and(Household::count())->toBe(1)->and($second->medicalRecords()->count())->toBe(0)
        ->and($second->vitalSigns()->count())->toBe(0);
    Livewire::actingAs(User::factory()->create())->test(PatientProfileForm::class, ['patientId' => $first->patient_id])
        ->assertSee($second->first_name)->assertSee(route('medical-records.show', ['record' => $second->patient_id]));
});

it('does not duplicate membership when the same household is saved again', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    Livewire::actingAs(User::factory()->create())->test(EditPatient::class, ['patient' => $patient])
        ->call('saveChanges')->assertHasNoErrors();
    expect($household->members()->count())->toBe(1)->and(PatientInformation::count())->toBe(1);
});

it('requires and audits household transfers without changing individual clinical records', function () {
    $old = Household::factory()->create();
    $next = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $old->id]);
    $dose = ImmunizationDose::factory()->create(['patient_id' => $patient->patient_id]);
    $form = Livewire::actingAs(User::factory()->create())->test(EditPatient::class, ['patient' => $patient])
        ->call('selectHousehold', $next->id)->call('saveChanges')->assertHasErrors('transferReason');
    expect($patient->fresh()->household_id)->toBe($old->id)->and($patient->registrationProfile()->exists())->toBeFalse();
    $form->set('transferReason', 'Moved residence')->call('saveChanges')->assertHasNoErrors();
    expect($patient->fresh()->household_id)->toBe($next->id)->and($dose->fresh()->patient_id)->toBe($patient->patient_id);
    $audit = PatientProfileAudit::where('action', 'household-membership-changed')->sole();
    expect($audit->details['from'])->toBe($old->id)->and($audit->details['to'])->toBe($next->id)
        ->and($audit->details['reason'])->toBe('Moved residence')
        ->and(DB::table('patient_profile_audits')->where('id', $audit->id)->value('details'))->not->toContain('Moved residence');
});

it('updates shared living conditions without overwriting member addresses or history', function () {
    $household = Household::factory()->create();
    $members = PatientInformation::factory()->count(2)->create(['household_id' => $household->id]);
    $addresses = $members->pluck('address')->all();
    Livewire::actingAs(User::factory()->create())->test(EditPatient::class, ['patient' => $members[0]])
        ->set('updateHousehold', true)->set('householdData.home_ownership', 'Own')
        ->set('householdData.address', 'New shared address')->call('saveChanges')->assertHasNoErrors();
    expect($household->fresh()->home_ownership)->toBe('Own')
        ->and($members->map(fn ($patient) => $patient->fresh()->address)->all())->toBe($addresses)
        ->and(PatientProfileAudit::where('action', 'household-updated')->sole()->details['before']['address'])->toBe($household->address);
});

it('keeps optional unknown answers nullable and discards inapplicable details', function () {
    newRegistrationForProfile()->set('profile.four_ps', 'No')->set('profile.four_ps_number', 'must not save')
        ->set('profile.pwd', 'No')->set('profile.pwd_number', 'must not save')
        ->set('profile.smoking', 'No')->set('profile.packs_per_year', 10)
        ->call('savePatient')->assertHasNoErrors();
    $profile = PatientProfile::sole();
    expect($profile->four_ps_number)->toBeNull()->and($profile->pwd_number)->toBeNull()
        ->and($profile->packs_per_year)->toBeNull()->and($profile->alcohol)->toBeNull();
});

it('shows conditional fields from the same schema used for validation', function () {
    newRegistrationForProfile(User::factory()->create(['role' => 'Admin']))
        ->assertDontSee('id="profile-four_ps_number"', false)->assertDontSee('id="profile-pwd_number"', false)
        ->assertDontSee('id="profile-packs_per_year"', false)->assertDontSee('id="profile-lmp"', false)
        ->set('profile.four_ps', 'Yes')->assertSee('id="profile-four_ps_number"', false)
        ->set('profile.pwd', 'Yes')->assertSee('id="profile-pwd_number"', false)
        ->set('profile.smoking', 'Yes')->assertSee('id="profile-packs_per_year"', false)
        ->set('profile.reproductive_applicable', 'Yes')->set('profile.pregnant', 'Yes')->assertSee('id="profile-lmp"', false)
        ->set('profile.pregnant', 'No')->assertDontSee('id="profile-lmp"', false);
});

it('shows child and adolescent sections for the selected category', function () {
    $form = newRegistrationForProfile(User::factory()->create(['role' => 'Admin']))
        ->set('birthdate', now('Asia/Manila')->subYears(3)->toDateString())
        ->call('selectCategory', 'Child')
        ->assertSee('id="profile-vaccination_status"', false)->assertDontSee('id="profile-smoking"', false)
        ->assertDontSee('id="profile-headss_home"', false);
    $form->set('birthdate', now('Asia/Manila')->subYears(15)->toDateString())
        ->call('selectCategory', 'Adolescent')
        ->assertDontSee('id="profile-vaccination_status"', false)->assertSee('id="profile-smoking"', false)
        ->assertSee('id="profile-headss_home"', false);
});

it('validates dates email income and numeric profile details', function (string $field, mixed $value, string $error) {
    $form = newRegistrationForProfile(User::factory()->create(['role' => 'Admin']))
        ->set('householdMode', 'new')->set('householdData', ['label' => 'Family', 'address' => 'Address'])
        ->set('profile.reproductive_applicable', 'Yes')->set('profile.pregnant', 'Yes')->set('profile.smoking', 'Yes');
    $form->set($field, $value)->call('savePatient')->assertHasErrors($error);
    expect(PatientInformation::count())->toBe(0)->and(Household::count())->toBe(0);
})->with([
    ['profile.email', 'invalid', 'profile.email'],
    ['profile.lmp', '2999-01-01', 'profile.lmp'],
    ['profile.packs_per_year', -1, 'profile.packs_per_year'],
    ['householdData.average_income', -1, 'householdData.average_income'],
    ['householdData.family_count', 1.5, 'householdData.family_count'],
]);

it('rejects an expected delivery date before the last menstrual period', function () {
    newRegistrationForProfile(User::factory()->create(['role' => 'Admin']))
        ->set('profile.reproductive_applicable', 'Yes')->set('profile.pregnant', 'Yes')
        ->set('profile.lmp', '2026-10-01')->set('profile.edc', '2026-09-01')
        ->call('savePatient')->assertHasErrors('profile.edc');
});

it('protects restricted fields from staff reads edits and history access', function () {
    $patient = PatientInformation::factory()->create(['birthdate' => now('Asia/Manila')->subYears(15)->toDateString()]);
    PatientProfile::factory()->create(['patient_id' => $patient->patient_id, 'headss_home' => 'Private home assessment', 'reproductive_applicable' => 'Yes', 'pregnant' => 'Unknown']);
    PatientProfileAudit::factory()->create(['patient_id' => $patient->patient_id, 'details' => ['before' => ['headss_home' => 'Private previous assessment']]]);
    $staff = User::factory()->create();
    $form = Livewire::actingAs($staff)->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->assertDontSee('Private home assessment')->assertDontSee('Adolescent HEADSS Assessment');
    expect($form->get('profile'))->not->toHaveKey('headss_home');
    $form->call('viewHistory')->assertDontSee('Private previous assessment')->call('editProfile')
        ->set('profile.headss_home', 'Forged response')->assertForbidden();
    expect($patient->registrationProfile->headss_home)->toBe('Private home assessment');
});

it('lets doctors update restricted assessments without changing household or consultations', function () {
    $patient = PatientInformation::factory()->create(['birthdate' => now('Asia/Manila')->subYears(15)->toDateString()]);
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->call('editProfile')->set('profile.headss_home', 'Home assessment')->set('profile.reproductive_applicable', 'Yes')
        ->set('profile.pregnant', 'Unknown')->call('reviewProfile')->assertHasNoErrors()->call('saveProfile')->assertHasNoErrors()
        ->assertSee('Home assessment')->assertSee('Patient profile saved successfully.');
    expect($patient->registrationProfile->headss_home)->toBe('Home assessment')
        ->and(PatientProfileAudit::where('action', 'sensitive-profile-accessed')->exists())->toBeTrue();
});

it('retains multiple doses and structured reported conditions over later updates', function () {
    newRegistrationForProfile()->set('selectedConditions', ['Diabetes', 'Hypertension'])->set('otherCondition', 'Reported thyroid condition')
        ->set('profile.tuberculosis', 'Yes')->set('profile.tb_treatment', 'Completed documented treatment')
        ->set('newDoses', [documentedDose(), documentedDose(['dose' => 'Dose 2'])])->call('savePatient')->assertHasNoErrors();
    $patient = PatientInformation::sole();
    expect($patient->conditions()->count())->toBe(3)->and($patient->conditions()->where('status', 'Reported')->count())->toBe(3)
        ->and($patient->immunizationDoses()->count())->toBe(2);
    $originalDose = $patient->immunizationDoses()->first();
    Livewire::actingAs(User::factory()->create())->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->call('editProfile')->set('selectedConditions', ['Diabetes'])->set('profile.tb_treatment', 'Follow-up assessment')
        ->set('newDoses', [documentedDose(['dose' => 'Dose 3', 'supersedes_id' => $originalDose->id])])
        ->call('saveProfile')->assertHasNoErrors()->assertSee('Dose 1')->assertSee('Dose 3');
    expect($patient->immunizationDoses()->count())->toBe(3)->and($originalDose->fresh()->dose)->toBe('Dose 1')
        ->and($patient->conditions()->where('name', 'Hypertension')->sole()->ended_at)->not->toBeNull();
    $before = PatientProfileAudit::where('action', 'profile-updated')->latest('id')->first()->details['before'];
    expect($before['tb_treatment'])->toBe('Completed documented treatment');
});

it('validates documented dose administration and custom vaccines', function (array $dose, string $error) {
    newRegistrationForProfile()->set('newDoses', [$dose])->call('savePatient')->assertHasErrors($error);
    expect(ImmunizationDose::count())->toBe(0)->and(PatientInformation::count())->toBe(0);
})->with([
    [documentedDose(['administered_at' => '']), 'newDoses.0.administered_at'],
    [documentedDose(['administered_at' => '2999-01-01']), 'newDoses.0.administered_at'],
    [documentedDose(['administered_at' => '1990-01-01']), 'newDoses.0.administered_at'],
    [documentedDose(['vaccine_name' => 'Others', 'other_vaccine' => '']), 'newDoses.0.other_vaccine'],
    [documentedDose(['status' => 'Pending']), 'newDoses.0.administered_at'],
]);

it('rolls back all registration tables when a later operation fails', function () {
    $otherDose = ImmunizationDose::factory()->create();
    newRegistrationForProfile()->set('householdMode', 'new')->set('householdData', ['label' => 'Family', 'address' => 'Address'])
        ->set('selectedConditions', ['Asthma'])->set('newDoses', [documentedDose(['supersedes_id' => $otherDose->id])])
        ->call('savePatient')->assertHasErrors('newDoses');
    expect(PatientInformation::count())->toBe(1)->and(Household::count())->toBe(0)
        ->and(PatientProfile::count())->toBe(0)->and(PatientCondition::count())->toBe(0)->and(PatientProfileAudit::count())->toBe(0);
});

it('rejects stale profile and household edits instead of overwriting newer changes', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    $staff = User::factory()->create();
    $first = Livewire::actingAs($staff)->test(EditPatient::class, ['patient' => $patient]);
    $second = Livewire::actingAs($staff)->test(EditPatient::class, ['patient' => $patient]);
    $first->set('profile.religion', 'First answer')->call('saveChanges')->assertHasNoErrors();
    $second->set('profile.religion', 'Stale answer')->call('saveChanges')->assertHasErrors('profile');
    expect($patient->registrationProfile->religion)->toBe('First answer');
    $edit = Livewire::actingAs($staff)->test(EditPatient::class, ['patient' => $patient]);
    $household->update(['version' => 2, 'home_ownership' => 'Own']);
    $edit->set('updateHousehold', true)->set('householdData.home_ownership', 'Rent')->call('saveChanges')->assertHasErrors('householdData');
    expect($household->fresh()->home_ownership)->toBe('Own');
});

it('checks permissions again on profile save and locks patient identity and versions', function () {
    $staff = User::factory()->create();
    $patient = PatientInformation::factory()->create();
    $form = Livewire::actingAs($staff)->test(PatientProfileForm::class, ['patientId' => $patient->patient_id]);
    expect(fn () => $form->set('patientId', 999))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => $form->set('profileVersion', 999))->toThrow(CannotUpdateLockedPropertyException::class);
    $staff->forceFill(['is_active' => false])->save();
    Livewire::actingAs($staff->fresh());
    $form->call('saveProfile')->assertForbidden();
    expect($patient->registrationProfile()->exists())->toBeFalse();
});

it('records a pending dose administration through the update action while retaining its original', function () {
    $dose = ImmunizationDose::factory()->create();
    Livewire::actingAs(User::factory()->create())->test(PatientProfileForm::class, ['patientId' => $dose->patient_id])
        ->call('recordDoseUpdate', $dose->id)->assertSet('editing', true)
        ->assertSet('newDoses.0.supersedes_id', $dose->id)->set('newDoses.0.status', 'Completed')
        ->set('newDoses.0.other_schedule', 'Documented administration')
        ->set('newDoses.0.administered_at', now('Asia/Manila')->toDateString())
        ->call('reviewProfile')->assertHasNoErrors()->call('saveProfile')->assertHasNoErrors();
    expect($dose->fresh()->status)->toBe('Pending')->and(ImmunizationDose::count())->toBe(2)
        ->and(ImmunizationDose::where('supersedes_id', $dose->id)->sole()->status)->toBe('Completed');
});

it('preserves restricted assessments during an authorized basic staff edit', function () {
    $patient = PatientInformation::factory()->create();
    PatientProfile::factory()->create(['patient_id' => $patient->patient_id, 'pregnant' => 'Yes', 'headss_home' => 'Prior confidential assessment']);
    Livewire::actingAs(User::factory()->create())->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->call('editProfile')->set('profile.birthplace', 'Muntinlupa')->call('saveProfile')->assertHasNoErrors();
    expect($patient->registrationProfile->pregnant)->toBe('Yes')
        ->and($patient->registrationProfile->headss_home)->toBe('Prior confidential assessment')
        ->and($patient->registrationProfile->birthplace)->toBe('Muntinlupa');
});

it('keeps historical adolescent assessments accessible to doctors as the patient ages', function () {
    $patient = PatientInformation::factory()->create();
    PatientProfileAudit::factory()->create(['patient_id' => $patient->patient_id, 'details' => ['before' => ['headss_home' => 'Historical adolescent assessment']]]);
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->assertDontSee('Historical adolescent assessment')->call('viewHistory')->assertSee('Historical adolescent assessment');
});

it('does not allow doctors to create or transfer household membership', function () {
    $patient = PatientInformation::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->call('editProfile')->set('householdMode', 'new')->set('householdData', ['label' => 'Forbidden household', 'address' => 'Address'])
        ->call('saveProfile')->assertForbidden();
    expect(Household::count())->toBe(0)->and($patient->fresh()->household_id)->toBeNull();
});
