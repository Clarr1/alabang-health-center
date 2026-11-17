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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-15 10:00:00 Asia/Manila'));
});

function categoryRegistration(string $category, int $age, ?User $staff = null): Testable
{
    return Livewire::actingAs($staff ?? User::factory()->create(['role' => 'Admin']))
        ->test(PatientInformationForm::class)
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set('first_name', 'Category')->set('last_name', 'Patient')
        ->set('birthdate', now('Asia/Manila')->subYears($age)->toDateString())
        ->set('address', 'Alabang, Muntinlupa City')->assertSet('patientCategory', $category);
}

it('opens with birthday entry and no category chooser or assessments', function () {
    Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->assertSet('patientCategory', null)
        ->assertDontSee('Continue to child registration')->assertDontSee('Back to categories')
        ->assertSee('id="patient-birthdate"', false)->assertSee('id="patient-first_name"', false)
        ->assertDontSee('id="profile-smoking"', false)->assertDontSee('id="household-mode"', false);
});

it('requires a birthdate instead of a manual category selection', function () {
    $form = Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set('first_name', 'Missing')->set('last_name', 'Birthday')->set('address', 'Alabang')
        ->call('savePatient')->assertHasErrors('birthdate');
    expect(PatientInformation::count())->toBe(0);
    $form->set('birthdate', '1990-01-01')->assertSet('patientCategory', 'Adult')
        ->call('savePatient')->assertHasNoErrors();
    expect(PatientInformation::sole()->patient_category)->toBe('Adult');
});

it('shows only the assessments for the calculated category', function (string $category, int $age, string $included, array $excluded) {
    $form = categoryRegistration($category, $age)
        ->assertSee('id="profile-'.$included.'"', false);
    foreach ($excluded as $field) {
        $form->assertDontSee('id="profile-'.$field.'"', false);
    }
    $form->assertSet('categoryMismatch', false)->assertDontSee('id="patient-category"', false)
        ->assertDontSee('id="category-override-reason"', false);
})->with([
    ['Child', 9, 'vaccination_status', ['smoking', 'headss_home', 'pregnant']],
    ['Adolescent', 15, 'headss_home', ['vaccination_status', 'delivery_manner', 'pregnant']],
    ['Adult', 36, 'smoking', ['vaccination_status', 'delivery_manner', 'headss_home']],
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
    $queue = app(QueueWorkflow::class)->addPatient($patient->patient_id);
    expect($queue->patient_id)->toBe($patient->patient_id);
    $this->actingAs($staff)->get(route('medical-records.show', ['record' => $patient->patient_id]))
        ->assertOk()->assertSee($category)->assertSee($existingMember->first_name);
    $form->call('resetForm')->assertSet('patientCategory', null)->assertSet('age', '')
        ->assertSee('id="patient-birthdate"', false);
})->with([
    ['Child', 4, 'development_assessment', 'Child development assessment'],
    ['Adolescent', 15, 'headss_home', 'Adolescent home assessment'],
    ['Adult', 36, 'smoking', 'No'],
]);

it('uses configured birthday boundaries including children ages six through nine', function (int $age, ?string $category) {
    expect(app(PatientCategories::class)->suggest(now('Asia/Manila')->subYears($age)->toDateString()))->toBe($category);
})->with([[0, 'Child'], [5, 'Child'], [6, 'Child'], [9, 'Child'], [10, 'Adolescent'], [19, 'Adolescent'], [20, 'Adult'], [150, 'Adult'], [151, null]]);

it('uses configuration rather than fixed age boundaries for suggestions', function () {
    config(['patient-registration.categories.Child.max_age' => 5, 'patient-registration.categories.Adolescent.min_age' => 6]);
    expect(app(PatientCategories::class)->suggest('2019-11-15'))->toBe('Adolescent');
});

it('rejects browser attempts to supply an override reason', function (string $reason) {
    categoryRegistration('Child', 7)->set('categoryOverrideReason', $reason)->assertForbidden();
    expect(PatientInformation::count())->toBe(0);
})->with(['', 'short', '          x']);

it('saves and audits the automatic category for registration staff', function (string $role) {
    $staff = User::factory()->create(['role' => $role]);
    categoryRegistration('Child', 7, $staff)
        ->call('reviewDetails')->assertHasNoErrors()->call('savePatient')->assertHasNoErrors();
    $patient = PatientInformation::sole();
    expect($patient->patient_category)->toBe('Child')->and($patient->category_override_reason)->toBeNull();
    $audit = PatientProfileAudit::where('action', 'patient-category-changed')->sole();
    expect($audit->user_id)->toBe($staff->user_id)->and($audit->details['age'])->toBe(7)
        ->and($audit->details['suggested'])->toBe('Child')->and($audit->details['to'])->toBe('Child');
})->with(['Admin', 'Local Health Unit Staff']);

it('rejects manual category changes even by administrators', function () {
    categoryRegistration('Adolescent', 15)->set('patientCategory', 'Adult')->assertForbidden();
    expect(PatientInformation::count())->toBe(0);
});

it('retains shared unsaved details and prunes inapplicable fields when birthday changes', function () {
    categoryRegistration('Child', 4)->set('profile.mother_name', 'Shared mother name')->set('profile.nutrition_status', 'Child status')
        ->set('birthdate', '2011-11-15')->assertSet('patientCategory', 'Adolescent')->assertSet('registrationStep', 1)
        ->assertSet('first_name', 'Category')->assertSet('profile.mother_name', 'Shared mother name')
        ->assertDontSee('id="profile-nutrition_status"', false);
    expect(PatientInformation::count())->toBe(0);
});

it('validates before advancing and prevents the browser from forging step progress', function () {
    $form = Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
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
    Livewire::actingAs($admin)->test(EditPatient::class, ['patient' => $patient])->assertSet('patientCategory', 'Adolescent')
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

it('refreshes the automatic age and category when the Manila date changes', function () {
    $form = categoryRegistration('Adolescent', 19)->set('birthdate', '2006-11-16')->assertSet('categoryMismatch', false);
    $this->travelTo(new DateTimeImmutable('2026-11-16 10:00:00 Asia/Manila'));
    $form->call('$refresh')->assertSet('patientCategory', 'Adult')->assertSet('age', 20)
        ->assertSet('categoryMismatch', false)->call('savePatient')->assertHasNoErrors();
    expect(PatientInformation::sole()->patient_category)->toBe('Adult');
});

it('requires a clinical choice for reproductive applicability for adolescents and adults', function (string $category, int $age) {
    categoryRegistration($category, $age)->set('sex', 'Male')->assertSee('Women of Reproductive Age')
        ->assertSee('id="profile-reproductive_applicable"', false)->assertDontSee('id="profile-pregnant"', false)
        ->set('profile.reproductive_applicable', 'Yes')->assertSee('id="profile-pregnant"', false)
        ->set('profile.pregnant', 'Unknown')->call('savePatient')->assertHasNoErrors();
    expect(PatientInformation::sole()->registrationProfile->pregnant)->toBe('Unknown');
})->with([['Adolescent', 15], ['Adult', 36]]);

it('continues to protect sensitive category assessments from registration staff', function () {
    categoryRegistration('Adolescent', 15, User::factory()->create())->assertDontSee('id="profile-headss_home"', false)
        ->assertDontSee('id="profile-reproductive_applicable"', false)->set('profile.headss_home', 'Forged assessment')->assertForbidden();
});

it('keeps historical profile categories read only for all staff', function (string $role) {
    $patient = PatientInformation::factory()->create(['birthdate' => '2019-11-15', 'patient_category' => 'Child', 'category_override_reason' => 'Approved child category age exception.']);
    $staff = User::factory()->create(['role' => $role]);
    Livewire::actingAs($staff)->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->call('editProfile')->assertDontSee('id="patient-category"', false)
        ->assertDontSee('id="category-override-reason"', false)
        ->set('profile.nutrition_status', 'Follow-up assessment')->call('saveProfile')->assertHasNoErrors();
    expect($patient->registrationProfile->nutrition_status)->toBe('Follow-up assessment')->and($patient->fresh()->patient_category)->toBe('Child');
    Livewire::actingAs($staff)->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])
        ->set('patientCategory', 'Adolescent')->assertForbidden();
})->with(['Admin', 'Local Health Unit Staff', 'Doctor']);

it('clears the age category and assessments for missing or invalid birthdays', function (string $birthdate, string $component) {
    $staff = User::factory()->create(['role' => 'Admin']);
    $patient = $component === EditPatient::class ? PatientInformation::factory()->create() : null;
    $form = Livewire::actingAs($staff)->test($component, $patient === null ? [] : ['patient' => $patient]);
    $form->set('birthdate', '2019-11-15')->set('profile.nutrition_status', 'Unsaved child assessment')
        ->set('profile.mother_name', 'Shared mother name')
        ->set('birthdate', $birthdate)->assertSet('age', '')->assertSet('patientCategory', null)
        ->assertSet('profile.mother_name', 'Shared mother name')->assertDontSee('id="profile-nutrition_status"', false)
        ->assertDontSee('id="profile-headss_home"', false)->assertDontSee('id="profile-smoking"', false)
        ->call($patient === null ? 'savePatient' : 'saveChanges')->assertHasErrors('birthdate');
    expect(PatientInformation::count())->toBe($patient === null ? 0 : 1);
    if ($patient !== null) {
        expect($patient->fresh()->birthdate)->toBe($patient->birthdate);
    }
    expect($form->get('profile'))->not->toHaveKey('nutrition_status');
})->with(['', 'not-a-date', '2026-02-30', '2026-11-16'])->with([PatientInformationForm::class, EditPatient::class]);

it('updates and saves the category when an existing birthday is corrected', function (string $birthdate, int $age, string $category, string $field) {
    $patient = PatientInformation::factory()->create(['patient_category' => 'Adult']);
    Livewire::actingAs(User::factory()->create(['role' => 'Admin']))->test(EditPatient::class, ['patient' => $patient])
        ->assertDontSee('id="patient-category"', false)->assertDontSee('id="category-override-reason"', false)
        ->set('birthdate', $birthdate)->assertSet('age', $age)->assertSet('patientCategory', $category)
        ->assertSee('id="profile-'.$field.'"', false)->call('reviewDetails')->assertHasNoErrors()
        ->call('saveChanges')->assertHasNoErrors();
    expect($patient->fresh()->patient_category)->toBe($category)->and($patient->fresh()->age)->toBe($age)
        ->and($patient->fresh()->category_override_reason)->toBeNull();
})->with([
    ['2017-11-15', 9, 'Child', 'vaccination_status'],
    ['2016-11-15', 10, 'Adolescent', 'headss_home'],
    ['2006-11-15', 20, 'Adult', 'smoking'],
]);

it('prevents overriding the calculated category on an existing patient', function (string $property, string $value) {
    $patient = PatientInformation::factory()->create(['patient_category' => 'Adult']);
    Livewire::actingAs(User::factory()->create(['role' => 'Admin']))->test(EditPatient::class, ['patient' => $patient])
        ->set($property, $value)->assertForbidden();
    expect($patient->fresh()->patient_category)->toBe('Adult')->and($patient->fresh()->category_override_reason)->toBeNull();
})->with([['patientCategory', 'Child'], ['categoryOverrideReason', 'Manual exception attempt.']]);

it('rejects birthdays older than the supported age limit', function (string $component) {
    $patient = $component === EditPatient::class ? PatientInformation::factory()->create() : null;
    Livewire::actingAs(User::factory()->create())->test($component, $patient === null ? [] : ['patient' => $patient])
        ->set('birthdate', '1875-11-15')->assertSet('age', 151)->assertSet('patientCategory', null)
        ->call($patient === null ? 'savePatient' : 'saveChanges')->assertHasErrors('birthdate');
})->with([PatientInformationForm::class, EditPatient::class]);

it('calculates leap day birthdays and exact category transitions using the Manila date', function (string $today, string $birthdate, int $age, string $category) {
    $this->travelTo(new DateTimeImmutable($today));
    Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->set('birthdate', $birthdate)->assertSet('age', $age)->assertSet('patientCategory', $category);
})->with([
    ['2026-02-28 10:00:00 Asia/Manila', '2016-02-29', 9, 'Child'],
    ['2026-03-01 10:00:00 Asia/Manila', '2016-02-29', 10, 'Adolescent'],
    ['2028-02-29 10:00:00 Asia/Manila', '2008-02-29', 20, 'Adult'],
    ['2026-11-14 16:00:00 UTC', '2016-11-15', 10, 'Adolescent'],
    ['2026-11-14 15:59:59 UTC', '2006-11-15', 19, 'Adolescent'],
    ['2026-11-14 16:00:00 UTC', '2006-11-15', 20, 'Adult'],
]);

it('rejects attempts to manually set the calculated age', function (string $component) {
    $patient = $component === EditPatient::class ? PatientInformation::factory()->create() : null;
    $form = Livewire::actingAs(User::factory()->create())->test($component, $patient === null ? [] : ['patient' => $patient])
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set('first_name', 'Automatic')->set('last_name', 'Age')->set('address', 'Alabang')
        ->set('birthdate', '2017-11-15')->assertSet('age', 9);
    expect(fn () => $form->set('age', 36))->toThrow(CannotUpdateLockedPropertyException::class);
})->with([PatientInformationForm::class, EditPatient::class]);
