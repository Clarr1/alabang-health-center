<?php

use App\Livewire\EditPatient;
use App\Livewire\PatientInformationForm;
use App\Livewire\PatientProfileForm;
use App\Models\Household;
use App\Models\PatientInformation;
use App\Models\PatientProfile;
use App\Models\PatientProfileAudit;
use App\Models\User;
use App\Services\PatientCategories;
use App\Services\QueueWorkflow;
use Database\Factories\MedicalRecordFactory;
use Database\Factories\VitalSignFactory;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-15 10:00:00 Asia/Manila'));
});

function categoryRegistration(string $category, int $age, ?User $staff = null): Testable
{
    return Livewire::actingAs($staff ?? User::factory()->create(['role' => 'Admin']))
        ->test(PatientInformationForm::class)->call('selectCategory', $category)
        ->set('first_name', 'Category')->set('last_name', 'Patient')
        ->set('birthdate', now('Asia/Manila')->subYears($age)->toDateString())
        ->set('address', 'Alabang, Muntinlupa City');
}

it('opens with category cards and no registration fields', function () {
    Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->assertSet('showCategorySelection', true)->assertSet('patientCategory', null)
        ->assertSee('Continue to child registration')->assertSee('Continue to adolescent registration')
        ->assertSee('Continue to adult registration')->assertDontSee('id="patient-first_name"', false)
        ->assertDontSee('id="profile-smoking"', false)->assertDontSee('id="household-mode"', false);
});

it('rejects unconfigured categories and missing category selections', function () {
    $form = Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->call('selectCategory', 'Other')->assertHasErrors('patientCategory')->assertSet('showCategorySelection', true);
    $form->set('first_name', 'Missing')->set('last_name', 'Category')->set('birthdate', '1990-01-01')->set('address', 'Alabang')
        ->call('savePatient')->assertHasErrors('patientCategory');
    expect(PatientInformation::count())->toBe(0);
});

it('shows only the selected category assessments even when the birthdate suggests another category', function (string $category, string $included, array $excluded) {
    $form = categoryRegistration($category, 36)->assertSet('patientCategory', $category)
        ->assertSee('id="profile-'.$included.'"', false);
    foreach ($excluded as $field) {
        $form->assertDontSee('id="profile-'.$field.'"', false);
    }
    if ($category !== 'Adult') {
        $form->assertSet('categoryMismatch', true)->assertSee('The selected category does not match the calculated age.');
    }
})->with([
    ['Child', 'vaccination_status', ['smoking', 'headss_home', 'pregnant']],
    ['Adolescent', 'headss_home', ['vaccination_status', 'delivery_manner', 'pregnant']],
    ['Adult', 'smoking', ['vaccination_status', 'delivery_manner', 'headss_home']],
]);

it('registers each category through its steps with shared household and patient record integration', function (string $category, int $age, string $field, string $value) {
    $staff = User::factory()->create(['role' => 'Admin']);
    $household = Household::factory()->create();
    $existingMember = PatientInformation::factory()->create(['household_id' => $household->id]);
    $form = categoryRegistration($category, $age, $staff)->assertSet('registrationStep', 1)
        ->call('continueRegistration')->assertHasNoErrors()->assertSet('registrationStep', 2)
        ->call('selectHousehold', $household->id)->set('householdRelationship', 'Family member')
        ->call('continueRegistration')->assertHasNoErrors()->assertSet('registrationStep', 3)
        ->set('profile.'.$field, $value)
        ->call('continueRegistration')->assertHasNoErrors()->assertSet('registrationStep', 4)
        ->set('profile.tuberculosis', 'Unknown')->set('selectedConditions', ['Asthma'])
        ->set('newDoses', [['vaccine_name' => 'IPV', 'schedule' => 'Other', 'other_schedule' => 'Documented schedule', 'dose' => '1', 'status' => 'Pending']])
        ->call('continueRegistration')->assertHasNoErrors()->assertSet('registrationStep', 5)->assertSet('showReview', true)
        ->assertSee('Review patient details')->assertSee($category);
    expect(PatientInformation::count())->toBe(1);
    $form->call('submitRegistration')->assertHasNoErrors()->assertDispatched('patient-information-saved');
    $patient = PatientInformation::findOrFail($form->get('registeredPatientId'));
    expect($patient->patient_category)->toBe($category)->and($patient->patient_id)->not->toBe($existingMember->patient_id)
        ->and($patient->household_id)->toBe($household->id)->and($patient->registrationProfile->{$field})->toBe($value)
        ->and($patient->registrationProfile->tuberculosis)->toBe('Unknown')->and($patient->immunizationDoses()->count())->toBe(1)
        ->and($patient->conditions()->sole()->status)->toBe('Reported');
    $queue = app(QueueWorkflow::class)->addPatient($patient->patient_id, 1);
    expect($queue->patient_id)->toBe($patient->patient_id);
    $this->actingAs($staff)->get(route('medical-records.show', ['record' => $patient->patient_id]))
        ->assertOk()->assertSee($category)->assertSee($existingMember->first_name);
    $form->call('resetForm')->assertSet('showCategorySelection', true)->assertSet('patientCategory', null);
})->with([
    ['Child', 4, 'development_assessment', 'Child development assessment'],
    ['Adolescent', 15, 'headss_home', 'Adolescent home assessment'],
    ['Adult', 36, 'smoking', 'No'],
]);

it('uses configured birthday boundaries and leaves ages six through nine unmatched', function (int $age, ?string $category) {
    expect(app(PatientCategories::class)->suggest(now('Asia/Manila')->subYears($age)->toDateString()))->toBe($category);
})->with([[0, 'Child'], [5, 'Child'], [6, null], [9, null], [10, 'Adolescent'], [19, 'Adolescent'], [20, 'Adult']]);

it('uses configuration rather than fixed age boundaries for suggestions', function () {
    config(['patient-registration.categories.Adolescent.min_age' => 6]);
    expect(app(PatientCategories::class)->suggest('2019-11-15'))->toBe('Adolescent');
});

it('requires a meaningful exception reason for mismatched ages', function (string $reason) {
    categoryRegistration('Child', 7)->assertSet('suggestedCategory', null)->assertSet('patientCategory', 'Child')
        ->set('categoryOverrideReason', $reason)->call('reviewDetails')->assertHasErrors('categoryOverrideReason')
        ->call('savePatient')->assertHasErrors('categoryOverrideReason');
    expect(PatientInformation::count())->toBe(0);
})->with(['', 'short', '          x']);

it('allows authorized staff to document and audit an age exception', function (string $role) {
    $staff = User::factory()->create(['role' => $role]);
    categoryRegistration('Child', 7, $staff)->set('categoryOverrideReason', 'Child assessment requested for this age exception.')
        ->call('reviewDetails')->assertHasNoErrors()->call('savePatient')->assertHasNoErrors();
    $patient = PatientInformation::sole();
    expect($patient->patient_category)->toBe('Child')->and($patient->category_override_reason)->toBe('Child assessment requested for this age exception.');
    $audit = PatientProfileAudit::where('action', 'patient-category-changed')->sole();
    expect($audit->user_id)->toBe($staff->user_id)->and($audit->details['age'])->toBe(7)
        ->and($audit->details['suggested'])->toBeNull()->and($audit->details['to'])->toBe('Child');
})->with(['Admin', 'Local Health Unit Staff']);

it('enforces category override permission on the backend', function () {
    $form = categoryRegistration('Adult', 15, User::factory()->create())
        ->set('categoryOverrideReason', 'An exception has been requested for this assessment.');
    Gate::define('patient-category-override', fn (User $user): bool => false);
    $form->call('savePatient')->assertForbidden();
    expect(PatientInformation::count())->toBe(0);
});

it('retains shared unsaved details when returning to the category chooser', function () {
    categoryRegistration('Child', 4)->set('profile.mother_name', 'Shared mother name')->set('profile.nutrition_status', 'Child status')
        ->call('backToCategories')->assertSet('showCategorySelection', true)->assertDontSee('id="patient-first_name"', false)
        ->call('selectCategory', 'Adolescent')->assertSet('showCategorySelection', false)->assertSet('registrationStep', 1)
        ->assertSet('first_name', 'Category')->assertSet('profile.mother_name', 'Shared mother name')
        ->assertDontSee('id="profile-nutrition_status"', false);
    expect(PatientInformation::count())->toBe(0);
});

it('validates before advancing and prevents the browser from forging step progress', function () {
    $form = Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)->call('selectCategory', 'Adult')
        ->call('continueRegistration')->assertHasErrors(['first_name', 'birthdate', 'address'])->assertSet('registrationStep', 1);
    expect(fn () => $form->set('registrationStep', 5))->toThrow(CannotUpdateLockedPropertyException::class);
    $form->call('goToRegistrationStep', 5)->assertStatus(422);
});

it('preserves previous category assessments and medical relationships when categories change', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['birthdate' => '2011-11-15', 'age' => 15, 'patient_category' => 'Child', 'category_override_reason' => 'Historical child category exception.', 'household_id' => $household->id]);
    PatientProfile::factory()->create(['patient_id' => $patient->patient_id, 'nutrition_status' => 'Prior child assessment', 'development_assessment' => 'Prior developmental assessment']);
    $vital = VitalSignFactory::new()->create(['patient_id' => $patient->patient_id]);
    $consultation = MedicalRecordFactory::new()->create(['patient_id' => $patient->patient_id, 'vital_signs_id' => $vital->vital_id]);
    $originalConsultation = $consultation->fresh()->getAttributes();
    $admin = User::factory()->create(['role' => 'Admin']);
    Livewire::actingAs($admin)->test(EditPatient::class, ['patient' => $patient])->set('patientCategory', 'Adolescent')
        ->set('profile.headss_home', 'New adolescent assessment')->call('reviewDetails')->assertHasNoErrors()
        ->call('saveChanges')->assertHasNoErrors();
    expect(PatientInformation::count())->toBe(1)->and($patient->fresh()->patient_category)->toBe('Adolescent')
        ->and($patient->fresh()->household_id)->toBe($household->id)->and($patient->registrationProfile->nutrition_status)->toBe('Prior child assessment')
        ->and($patient->registrationProfile->headss_home)->toBe('New adolescent assessment')
        ->and($consultation->fresh()->getAttributes())->toBe($originalConsultation);
    Livewire::actingAs($admin)->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->assertDontSee('Prior child assessment')->call('viewHistory')->assertSee('Prior child assessment');
});

it('leaves legacy records unchanged when viewed and suggests their appropriate category', function () {
    $patient = PatientInformation::factory()->create();
    Livewire::actingAs(User::factory()->create())->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->assertSet('patientCategory', 'Adult')->assertSet('originalPatientCategory', null);
    expect($patient->fresh()->patient_category)->toBeNull()->and(PatientInformation::count())->toBe(1);
});

it('warns as a patient ages without silently changing the recorded category', function () {
    $form = categoryRegistration('Adolescent', 19)->set('birthdate', '2006-11-16')->assertSet('categoryMismatch', false);
    $this->travelTo(new DateTimeImmutable('2026-11-16 10:00:00 Asia/Manila'));
    $form->call('$refresh')->assertSet('patientCategory', 'Adolescent')->assertSet('suggestedCategory', 'Adult')
        ->assertSet('categoryMismatch', true)->assertSee('The selected category does not match the calculated age.');
});

it('requires a clinical choice for reproductive applicability independently of category or sex', function () {
    categoryRegistration('Adolescent', 15)->set('sex', 'Male')->assertDontSee('id="profile-pregnant"', false)
        ->set('profile.reproductive_applicable', 'Yes')->assertSee('id="profile-pregnant"', false)
        ->set('profile.pregnant', 'Unknown')->call('savePatient')->assertHasNoErrors();
    expect(PatientInformation::sole()->registrationProfile->pregnant)->toBe('Unknown');
});

it('continues to protect sensitive category assessments from registration staff', function () {
    categoryRegistration('Adolescent', 15, User::factory()->create())->assertDontSee('id="profile-headss_home"', false)
        ->assertDontSee('id="profile-reproductive_applicable"', false)->set('profile.headss_home', 'Forged assessment')->assertForbidden();
});

it('lets doctors update an approved exception profile without changing the approved category', function () {
    $patient = PatientInformation::factory()->create(['birthdate' => '2019-11-15', 'patient_category' => 'Child', 'category_override_reason' => 'Approved child category age exception.']);
    $doctor = User::factory()->create(['role' => 'Doctor']);
    Livewire::actingAs($doctor)->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->call('editProfile')->set('profile.nutrition_status', 'Follow-up assessment')->call('saveProfile')->assertHasNoErrors();
    expect($patient->registrationProfile->nutrition_status)->toBe('Follow-up assessment')->and($patient->fresh()->patient_category)->toBe('Child');
    Livewire::actingAs($doctor)->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->set('patientCategory', 'Adolescent')->assertForbidden();
});
