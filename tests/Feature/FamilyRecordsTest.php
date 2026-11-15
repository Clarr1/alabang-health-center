<?php

use App\Livewire\FamilyRecord;
use App\Livewire\FamilyTable;
use App\Livewire\MedicalRecordDetails;
use App\Livewire\PatientInformationForm;
use App\Livewire\PatientProfileForm;
use App\Livewire\PatientTable;
use App\Models\Household;
use App\Models\ImmunizationDose;
use App\Models\MedicalRecord;
use App\Models\PatientCondition;
use App\Models\PatientInformation;
use App\Models\PatientProfile;
use App\Models\PatientProfileAudit;
use App\Models\User;
use App\Models\VitalSign;
use Database\Factories\MedicalRecordFactory;
use Database\Factories\VitalSignFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/** @param array<string, mixed> $attributes */
function familyConsultation(PatientInformation $patient, array $attributes = []): MedicalRecord
{
    $vital = VitalSignFactory::new()->create(['patient_id' => $patient->patient_id]);

    return MedicalRecordFactory::new()->create(['patient_id' => $patient->patient_id, 'vital_signs_id' => $vital->vital_id, ...$attributes]);
}

it('provides individual and family views while retaining legacy patients without households', function () {
    $household = Household::factory()->create();
    $member = PatientInformation::factory()->create(['household_id' => $household->id]);
    $legacy = PatientInformation::factory()->create(['first_name' => 'Legacy patient']);
    $this->actingAs(User::factory()->create())->get(route('patients.index'))->assertOk()
        ->assertSee('Individual Patient Records')->assertSee('Family Records')->assertSee($household->family_number)
        ->assertSee('Legacy patient')->assertSee('Not assigned');
    Livewire::test(PatientTable::class)->set('search', (string) $legacy->patient_id)
        ->call('viewPatient', $legacy->patient_id)->assertSet('selectedPatientId', $legacy->patient_id)->assertSee('Patient Details');
    $this->get(route('medical-records.show', $legacy))->assertOk()->assertSee('Back to Medical Records');
    expect($member->fresh()->household_id)->toBe($household->id)->and($legacy->fresh()->household_id)->toBeNull();
});

it('lists households with distinct family numbers and accurate member totals', function () {
    $single = Household::factory()->create(['label' => 'Lopez Family']);
    $multiple = Household::factory()->create(['label' => 'Lopez Family']);
    PatientInformation::factory()->create(['household_id' => $single->id]);
    PatientInformation::factory()->count(3)->create(['household_id' => $multiple->id]);
    Livewire::actingAs(User::factory()->create())->test(FamilyTable::class)
        ->assertSee($single->family_number)->assertSee($multiple->family_number);
    $table = Livewire::test(FamilyTable::class);
    expect($table->instance()->households->pluck('members_count', 'id')->all())->toBe([$single->id => 1, $multiple->id => 3]);
});

it('searches households by their family number name and address', function (string $field) {
    $matching = Household::factory()->create(['label' => 'Santos Household', 'address' => 'Sampaguita Lane']);
    $other = Household::factory()->create(['label' => 'Other Household', 'address' => 'Different Road']);
    Livewire::actingAs(User::factory()->create())->test(FamilyTable::class)
        ->call('setPage', 2)->set('search', $matching->{$field})->assertSet('paginators.page', 1)
        ->assertSee($matching->family_number)->assertDontSee($other->family_number);
})->with(['family_number', 'label', 'address']);

it('paginates filters and clears the household directory', function () {
    $occupied = Household::factory()->create(['label' => 'A occupied household']);
    PatientInformation::factory()->create(['household_id' => $occupied->id]);
    Household::factory()->count(11)->create();
    $table = Livewire::actingAs(User::factory()->create())->test(FamilyTable::class);
    expect($table->instance()->households->total())->toBe(12)->and($table->instance()->households->count())->toBe(10);
    $table->call('setPage', 2)->set('memberFilter', 'with-members')->assertSet('paginators.page', 1);
    expect($table->instance()->households->total())->toBe(1);
    $table->set('memberFilter', 'without-members')->assertDontSee($occupied->family_number);
    expect($table->instance()->households->total())->toBe(11);
    $table->set('search', 'No such household')->assertSee('No households found.')
        ->call('resetFilters')->assertSet('search', '')->assertSet('memberFilter', '');
});

it('shows household living conditions and members without loading their clinical histories', function () {
    $household = Household::factory()->create(['label' => 'Family overview', 'water_source' => 'Communal Faucet', 'family_count' => 2, 'average_income' => 12345]);
    $members = PatientInformation::factory()->count(2)->create(['household_id' => $household->id, 'household_relationship' => 'Daughter']);
    $members[1]->update(['status' => 'Inactive']);
    familyConsultation($members[0], ['diagnosis' => 'Hidden until member selection']);
    $this->actingAs(User::factory()->create())->get(route('families.show', $household))->assertOk()
        ->assertSee('Communal Faucet')->assertSee('12,345.00')->assertSee($members[0]->first_name)
        ->assertSee($members[1]->first_name)->assertSee('Daughter')->assertSee('Active members')
        ->assertSee('Add Family Member')->assertDontSee('Hidden until member selection')
        ->assertDontSeeLivewire(MedicalRecordDetails::class);
    expect($household->members()->count())->toBe(2);
});

it('handles households without members and missing households', function () {
    $household = Household::factory()->create();
    $this->actingAs(User::factory()->create())->get(route('families.show', $household))->assertOk()->assertSee('No registered members in this household.');
    $this->get(route('families.show', 999999))->assertNotFound();
});

it('keeps member consultations vitals profiles immunizations conditions and followups separate', function () {
    $household = Household::factory()->create();
    $members = PatientInformation::factory()->count(2)->create(['household_id' => $household->id]);
    $staff = User::factory()->create();
    foreach ($members as $index => $patient) {
        $vital = VitalSignFactory::new()->create(['patient_id' => $patient->patient_id, 'blood_pressure' => $index === 0 ? '111/71' : '133/83']);
        MedicalRecordFactory::new()->create(['patient_id' => $patient->patient_id, 'vital_signs_id' => $vital->vital_id, 'diagnosis' => 'Member '.$index.' diagnosis', 'prescription' => 'Member '.$index.' medicine']);
        PatientProfile::factory()->create(['patient_id' => $patient->patient_id, 'pertinent_information' => 'Member '.$index.' history']);
        ImmunizationDose::factory()->create(['patient_id' => $patient->patient_id, 'remarks' => 'Member '.$index.' vaccine record']);
        PatientCondition::factory()->create(['patient_id' => $patient->patient_id, 'name' => 'Member '.$index.' condition']);
        $patient->followUps()->create(['purpose' => 'Member '.$index.' followup', 'scheduled_at' => now()->addDays(2), 'mobile' => '09171234567', 'created_by' => $staff->user_id]);
    }
    foreach ($members as $index => $patient) {
        $other = 1 - $index;
        $this->actingAs($staff)->get(route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $patient->patient_id]))->assertOk()
            ->assertSee('Member '.$index.' diagnosis')->assertSee('Member '.$index.' medicine')->assertSee('Member '.$index.' history')
            ->assertSee('Member '.$index.' vaccine record')->assertSee('Member '.$index.' condition')->assertSee('Member '.$index.' followup')
            ->assertSee($index === 0 ? '111/71' : '133/83')
            ->assertDontSee('Member '.$other.' diagnosis')->assertDontSee('Member '.$other.' medicine')->assertDontSee('Member '.$other.' history')
            ->assertDontSee('Member '.$other.' vaccine record')->assertDontSee('Member '.$other.' condition')->assertDontSee('Member '.$other.' followup')
            ->assertDontSee($index === 0 ? '133/83' : '111/71')->assertSee('Back to Family Record');
    }
    expect(MedicalRecord::count())->toBe(2)->and(VitalSign::count())->toBe(2)->and(PatientInformation::count())->toBe(2);
});

it('switches and closes the selected member summary', function () {
    $household = Household::factory()->create();
    $members = PatientInformation::factory()->count(2)->create(['household_id' => $household->id]);
    foreach ($members as $index => $patient) {
        familyConsultation($patient, ['diagnosis' => 'Selected member '.$index]);
    }
    $form = Livewire::actingAs(User::factory()->create())->test(FamilyRecord::class, ['household' => $household])
        ->call('selectMember', $members[0]->patient_id)->assertSet('selectedMemberId', $members[0]->patient_id)
        ->assertSee('Selected member 0')->assertDontSee('Selected member 1')
        ->call('selectMember', $members[1]->patient_id)->assertSet('selectedMemberId', $members[1]->patient_id)
        ->assertSee('Selected member 1')->assertDontSee('Selected member 0')
        ->call('closeMember')->assertSet('selectedMemberId', null)->assertDontSeeLivewire(MedicalRecordDetails::class);
    expect(MedicalRecord::count())->toBe(2);
});

it('orders consultations newest first and retains their complete original details', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    familyConsultation($patient, ['consultation_date' => '2026-01-01', 'diagnosis' => 'Older clinical entry']);
    $new = familyConsultation($patient, ['consultation_date' => '2026-02-01', 'diagnosis' => 'Newest clinical entry', 'chief_complaint' => 'Documented complaint', 'treatment' => 'Documented treatment', 'laboratory_requests' => 'Requested laboratory test']);
    $this->actingAs(User::factory()->create())->get(route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $patient]))->assertOk()
        ->assertSeeInOrder(['Newest clinical entry', 'Older clinical entry'])->assertSee('Documented complaint')->assertSee('Documented treatment')
        ->assertSee('Requested laboratory test')->assertSee('View original entry')->assertSee('consultation-modal-title-'.$new->medical_record_id, false);
});

it('rejects members outside the selected household in routes summaries and nested profiles', function () {
    $household = Household::factory()->create();
    $outsider = PatientInformation::factory()->create();
    $this->actingAs(User::factory()->create())->get(route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $outsider]))->assertNotFound();
    expect(fn () => Livewire::test(FamilyRecord::class, ['household' => $household])->call('selectMember', $outsider->patient_id))->toThrow(ModelNotFoundException::class);
    expect(fn () => Livewire::test(PatientProfileForm::class, ['patientId' => $outsider->patient_id, 'familyHouseholdId' => $household->id]))->toThrow(ModelNotFoundException::class);
});

it('does not expose mislinked vital signs or followup consultations from another patient', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    $other = PatientInformation::factory()->create(['household_id' => $household->id]);
    $otherVital = VitalSignFactory::new()->create(['patient_id' => $other->patient_id, 'blood_pressure' => '199/99']);
    MedicalRecordFactory::new()->create(['patient_id' => $patient->patient_id, 'vital_signs_id' => $otherVital->vital_id, 'diagnosis' => 'Own consultation']);
    $otherRecord = familyConsultation($other, ['diagnosis' => 'Other private diagnosis']);
    $staff = User::factory()->create();
    $patient->followUps()->create(['medical_record_id' => $otherRecord->medical_record_id, 'purpose' => 'Own followup', 'scheduled_at' => now(), 'mobile' => '09171234567', 'created_by' => $staff->user_id]);
    $this->actingAs($staff)->get(route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $patient]))->assertOk()
        ->assertSee('Own consultation')->assertSee('Own followup')->assertDontSee('Other private diagnosis')->assertDontSee('199/99');
});

it('requires current household membership on subsequent family and nested requests', function (string $component) {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    Livewire::actingAs(User::factory()->create());
    $form = match ($component) {
        'family' => Livewire::test(FamilyRecord::class, ['household' => $household])->call('selectMember', $patient->patient_id),
        'medical' => Livewire::test(MedicalRecordDetails::class, ['record' => $patient, 'familyHouseholdId' => $household->id]),
        'profile' => Livewire::test(PatientProfileForm::class, ['patientId' => $patient->patient_id, 'familyHouseholdId' => $household->id]),
    };
    $patient->update(['household_id' => null]);
    expect(fn () => $form->call('$refresh'))->toThrow(ModelNotFoundException::class);
})->with(['family', 'medical', 'profile']);

it('locks the household and member context against client changes', function (string $component, string $property) {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    Livewire::actingAs(User::factory()->create());
    $form = match ($component) {
        'family' => Livewire::test(FamilyRecord::class, ['household' => $household]),
        'medical' => Livewire::test(MedicalRecordDetails::class, ['record' => $patient, 'familyHouseholdId' => $household->id]),
        'profile' => Livewire::test(PatientProfileForm::class, ['patientId' => $patient->patient_id, 'familyHouseholdId' => $household->id]),
    };
    expect(fn () => $form->set($property, 99999))->toThrow(CannotUpdateLockedPropertyException::class);
})->with([['family', 'householdId'], ['family', 'selectedMemberId'], ['medical', 'patientId'], ['medical', 'familyHouseholdId'], ['profile', 'patientId'], ['profile', 'familyHouseholdId']]);

it('preselects and retains the existing household when registering additional family members', function () {
    $household = Household::factory()->create();
    $existing = PatientInformation::factory()->create(['household_id' => $household->id]);
    $record = familyConsultation($existing);
    $this->actingAs(User::factory()->create())->get(route('patient.information-form', ['household' => $household->id]))->assertOk()->assertSee('Adding a family member to')->assertSee($household->family_number);
    $form = Livewire::withQueryParams(['household' => $household->id])->test(PatientInformationForm::class)
        ->assertSet('showCategorySelection', true)->assertSet('householdMode', 'existing')->assertSet('householdId', $household->id)
        ->call('selectCategory', 'Adult')->set('first_name', 'New family member')->set('last_name', 'Lopez')
        ->set('birthdate', '1995-01-01')->set('address', 'Member address')->set('householdRelationship', 'Son')
        ->call('reviewDetails')->assertHasNoErrors()->call('savePatient')->assertHasNoErrors()->assertSee('Patient registered successfully.');
    $new = PatientInformation::whereKeyNot($existing->patient_id)->sole();
    expect($new->household_id)->toBe($household->id)->and($new->household_relationship)->toBe('Son')
        ->and($new->patient_id)->not->toBe($existing->patient_id)->and(Household::count())->toBe(1)
        ->and($household->members()->count())->toBe(2)->and($new->medicalRecords()->count())->toBe(0)
        ->and($record->fresh()->patient_id)->toBe($existing->patient_id);
    $form->call('resetForm')->assertSet('householdMode', 'existing')->assertSet('householdId', $household->id)->assertSet('showCategorySelection', true);
});

it('keeps sensitive assessments out of staff responses and state but permits clinicians', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id, 'birthdate' => now()->subYears(15)->toDateString(), 'patient_category' => 'Adolescent']);
    PatientProfile::factory()->create(['patient_id' => $patient->patient_id, 'headss_home' => 'Confidential HEADSS narrative', 'reproductive_applicable' => 'Yes', 'family_planning' => 'Confidential reproductive narrative']);
    $this->actingAs(User::factory()->create())->get(route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $patient]))->assertOk()
        ->assertDontSee('Confidential HEADSS narrative')->assertDontSee('Confidential reproductive narrative');
    $form = Livewire::test(PatientProfileForm::class, ['patientId' => $patient->patient_id, 'familyHouseholdId' => $household->id]);
    expect($form->get('profile'))->not->toHaveKeys(['headss_home', 'family_planning']);
    $this->actingAs(User::factory()->create(['role' => 'Doctor']))->get(route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $patient]))->assertOk()
        ->assertSee('Confidential HEADSS narrative')->assertSee('Confidential reproductive narrative');
});

it('rejects attempts to create or select a different household in add member registration', function (string $mode) {
    $household = Household::factory()->create();
    $other = Household::factory()->create();
    Livewire::actingAs(User::factory()->create());
    $form = Livewire::withQueryParams(['household' => $household->id])->test(PatientInformationForm::class)
        ->call('selectCategory', 'Adult')->assertDontSee('Create a household')
        ->set('first_name', 'Additional')->set('last_name', 'Member')->set('birthdate', '1995-01-01')->set('address', 'Shared address')
        ->set('householdMode', $mode)->set('householdId', $other->id)->call('savePatient')->assertHasErrors('householdId');
    expect(PatientInformation::count())->toBe(0)->and(Household::count())->toBe(2);
})->with(['new', 'none', 'existing']);

it('rejects missing and malformed registration household identifiers', function (mixed $identifier) {
    Livewire::actingAs(User::factory()->create());
    Livewire::withQueryParams(['household' => $identifier])->test(PatientInformationForm::class)->assertHasErrors('household');
    expect(PatientInformation::count())->toBe(0)->and(Household::count())->toBe(0);
})->with([999999, 'not-a-household']);

it('rejects profile writes from a stale family page after membership changes', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    $form = Livewire::actingAs(User::factory()->create())->test(PatientProfileForm::class, ['patientId' => $patient->patient_id, 'familyHouseholdId' => $household->id])
        ->call('editProfile')->set('profile.religion', 'Stale change');
    $patient->update(['household_id' => null]);
    expect(fn () => $form->call('saveProfile'))->toThrow(ModelNotFoundException::class);
    expect($patient->registrationProfile()->exists())->toBeFalse();
});

it('allows a documented household transfer from the family profile and returns to the overview', function () {
    $old = Household::factory()->create();
    $next = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $old->id]);
    $record = familyConsultation($patient);
    Livewire::actingAs(User::factory()->create())->test(PatientProfileForm::class, ['patientId' => $patient->patient_id, 'familyHouseholdId' => $old->id])
        ->call('editProfile')->call('selectHousehold', $next->id)->set('transferReason', 'Moved to another household')
        ->call('saveProfile')->assertHasNoErrors()->assertRedirect(route('families.show', $old));
    expect($patient->fresh()->household_id)->toBe($next->id)->and($record->fresh()->patient_id)->toBe($patient->patient_id)
        ->and($old->members()->count())->toBe(0)->and($next->members()->count())->toBe(1);
});

it('filters restricted values from profile history within family records', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    PatientProfileAudit::factory()->create(['patient_id' => $patient->patient_id, 'details' => ['before' => ['birthplace' => 'Historical birthplace', 'headss_home' => 'Historical confidential assessment']]]);
    Livewire::actingAs(User::factory()->create())->test(PatientProfileForm::class, ['patientId' => $patient->patient_id, 'familyHouseholdId' => $household->id])
        ->call('viewHistory')->assertSee('Historical birthplace')->assertDontSee('Historical confidential assessment');
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(PatientProfileForm::class, ['patientId' => $patient->patient_id, 'familyHouseholdId' => $household->id])
        ->call('viewHistory')->assertSee('Historical confidential assessment');
});

it('authorizes family routes and hides registration actions from doctors', function (string $role) {
    $household = Household::factory()->create();
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('families.index'))->assertOk();
    $response = $this->get(route('families.show', $household))->assertOk();
    if ($role === 'Doctor') {
        $response->assertDontSee('Add Family Member');
        $this->get(route('patient.information-form', ['household' => $household->id]))->assertForbidden();
    } else {
        $response->assertSee('Add Family Member');
    }
})->with(['Admin', 'Doctor', 'Local Health Unit Staff']);

it('blocks guests inactive accounts and unassigned roles from family medical records', function () {
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    $urls = [route('families.index'), route('families.show', $household), route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $patient])];
    foreach ($urls as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
    foreach ([['role' => null], ['is_active' => false]] as $attributes) {
        $this->actingAs(User::factory()->create()->forceFill($attributes));
        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }
    }
});

it('reauthorizes subsequent household requests after access is revoked', function () {
    $staff = User::factory()->create();
    $form = Livewire::actingAs($staff)->test(FamilyTable::class);
    $staff->forceFill(['is_active' => false])->save();
    Livewire::actingAs($staff->fresh());
    $form->call('resetFilters')->assertForbidden();
});

it('audits household and individual member access with the correct actor and identifiers', function () {
    $staff = User::factory()->create();
    $household = Household::factory()->create();
    $patient = PatientInformation::factory()->create(['household_id' => $household->id]);
    Livewire::actingAs($staff)->test(FamilyRecord::class, ['household' => $household])->call('selectMember', $patient->patient_id);
    $overview = PatientProfileAudit::where('action', 'family-record-accessed')->sole();
    $member = PatientProfileAudit::where('action', 'family-member-record-accessed')->sole();
    expect($overview->household_id)->toBe($household->id)->and($overview->patient_id)->toBeNull()
        ->and($overview->user_id)->toBe($staff->user_id)->and($member->patient_id)->toBe($patient->patient_id)
        ->and($member->household_id)->toBe($household->id)->and($member->user_id)->toBe($staff->user_id);
});
