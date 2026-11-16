<?php

use App\Livewire\MedicalRecordsForm;
use App\Livewire\PatientVitalSignForm;
use App\Livewire\VitalSignsForm;
use App\Models\Diagnosis;
use App\Models\Household;
use App\Models\MedicalRecord;
use App\Models\PatientCondition;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\SurveillanceClassification;
use App\Models\Symptom;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\IndividualTreatmentRecord;
use App\Services\SurveillanceAdministration;
use App\Services\SurveillanceEngine;
use Database\Factories\MedicalRecordFactory;
use Database\Factories\VitalSignFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** @return array{0: User, 1: Queue, 2: Testable} */
function itrConsultation(): array
{
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = Queue::factory()->create(['status' => 'Ready for Consultation']);
    VitalSignFactory::new()->create(['queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id, 'recorded_by' => $doctor->user_id]);
    $form = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $queue->queue_id);

    return [$doctor, $queue, $form];
}

it('saves every official clinical section and displays only the individual family members records', function () {
    Mail::fake();
    Notification::fake();
    Bus::fake();
    Http::preventStrayRequests();
    Http::fake();
    [$doctor, $queue, $form] = itrConsultation();
    $household = Household::factory()->create();
    $patient = $queue->patient;
    $patient->update(['household_id' => $household->id]);
    $other = PatientInformation::factory()->create(['household_id' => $household->id]);
    MedicalRecordFactory::new()->create(['patient_id' => $other->patient_id, 'diagnosis' => 'Other family member private visit']);
    $previous = MedicalRecordFactory::new()->create(['patient_id' => $patient->patient_id, 'diagnosis' => 'Previous visit retained', 'past_medical_history' => 'Previous history preserved']);
    $condition = PatientCondition::factory()->create(['patient_id' => $patient->patient_id, 'notes' => 'Longitudinal information retained']);
    $notes = [];
    foreach (IndividualTreatmentRecord::NOTES as $field => $label) {
        $notes[$field] = 'Visit documentation for '.$field;
    }
    $exam = [];
    foreach (IndividualTreatmentRecord::EXAMINATIONS as $group => $options) {
        $exam[$group] = ['findings' => $options, 'other' => 'Additional '.$group.' findings'];
    }
    $symptomIds = Symptom::whereIn('name', ['Fever', 'Cough'])->pluck('id')->all();
    $form->set('clinicalNotes', $notes)->set('physical_examination', $exam)
        ->set('chief_complaint', "Patient's own words: fever and cough.")->set('selectedSymptomIds', $symptomIds)
        ->set('diagnosis', 'Clinician assessment wording')->set('treatment', 'Treatment and interventions')
        ->set('prescription', 'Medication instructions')->set('laboratory_requests', 'Requested investigation')
        ->set('follow_up_date', now('Asia/Manila')->addDays(3)->toDateString())
        ->call('saveMedicalRecord')->assertHasNoErrors();
    $record = $queue->fresh()->medicalRecord;
    foreach ($notes as $field => $value) {
        expect($record->{$field})->toBe($value);
    }
    expect($record->physical_examination)->toBe($exam)
        ->and($record->symptoms->modelKeys())->toEqualCanonicalizing($symptomIds)
        ->and($record->consultation_date->toDateString())->toBe($queue->queue_date->toDateString())
        ->and($record->encounter_at->equalTo($queue->created_at))->toBeTrue()
        ->and($previous->fresh()->past_medical_history)->toBe('Previous history preserved')
        ->and($condition->fresh()->notes)->toBe('Longitudinal information retained');
    $response = $this->actingAs($doctor)->get(route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $patient->patient_id]));
    $response->assertOk()->assertSee('Previous visit retained')->assertSee($household->family_number)
        ->assertDontSee('Other family member private visit');
    foreach ($notes as $value) {
        $response->assertSee($value);
    }
    foreach ($exam as $group) {
        $response->assertSee($group['other']);
    }
    expect(DB::table('clinical_record_audits')->where('action', 'consultation-completed')->where('queue_id', $queue->queue_id)->count())->toBe(1);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

it('saves additional measurements with calculated bmi and shows the same visit in consultation', function () {
    $patient = PatientInformation::factory()->create();
    $queue = Queue::factory()->create(['patient_id' => $patient->patient_id, 'status' => 'Vital Signs']);
    $previous = VitalSignFactory::new()->create(['patient_id' => $patient->patient_id, 'weight' => 48, 'bmi' => 20]);
    $staff = User::factory()->create();
    Livewire::actingAs($staff)->test(VitalSignsForm::class, ['patient_id' => $patient->patient_id, 'queue_id' => $queue->queue_id])
        ->set('height', 160)->set('weight', 55)->set('bmi', 99)
        ->set('oxygen_saturation', 98)->set('waistline', 72)
        ->set('nutritional_status', 'Staff assessed status')->set('nutrition_assessment_method', 'Adult BMI')
        ->call('saveVitalSigns')->assertHasNoErrors();
    $vital = $queue->fresh()->vitalSign;
    expect((float) $vital->bmi)->toBe(21.5)->and((float) $vital->oxygen_saturation)->toBe(98.0)
        ->and((float) $vital->waistline)->toBe(72.0)->and($vital->recorded_at->diffInSeconds(now()) < 5)->toBeTrue()
        ->and($previous->fresh()->weight)->toEqual(48)->and(VitalSign::where('patient_id', $patient->patient_id)->count())->toBe(2);
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(MedicalRecordsForm::class)
        ->call('startConsultation', $queue->queue_id)->assertSee('Staff assessed status')->assertSee('72 cm')
        ->assertSet('vital_signs_id', $vital->vital_id);
});

it('rejects invalid official measurements without advancing the queue', function (string $field, mixed $value) {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    Livewire::actingAs(User::factory()->create())->test(VitalSignsForm::class, ['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id])
        ->set($field, $value)->call('saveVitalSigns')->assertHasErrors($field)->assertSet($field, $value);
    expect($queue->fresh()->status)->toBe('Vital Signs')->and(VitalSign::count())->toBe(0)
        ->and(DB::table('clinical_record_audits')->count())->toBe(0);
})->with([
    ['oxygen_saturation', 101], ['oxygen_saturation', -1], ['waistline', 0], ['waistline', 301],
    ['blood_pressure', '0/0'], ['blood_pressure', '80/120'], ['height', 0], ['weight', -1],
]);

it('requires the nutritional assessment method and never applies adult bmi categories to a child', function () {
    $patient = PatientInformation::factory()->create(['birthdate' => now()->subYears(10)->toDateString()]);
    $queue = Queue::factory()->create(['patient_id' => $patient->patient_id, 'status' => 'Vital Signs']);
    $form = Livewire::actingAs(User::factory()->create())->test(VitalSignsForm::class, ['patient_id' => $patient->patient_id, 'queue_id' => $queue->queue_id])
        ->set('height', 130)->set('weight', 30)->assertSee('Age-specific assessment required')
        ->set('nutritional_status', 'Clinician growth assessment')
        ->call('saveVitalSigns')->assertHasErrors('nutrition_assessment_method')
        ->set('nutrition_assessment_method', 'Adult BMI')->call('saveVitalSigns')->assertHasErrors('nutrition_assessment_method')
        ->set('nutrition_assessment_method', 'Age-specific growth assessment')->call('saveVitalSigns')->assertHasNoErrors();
    expect($queue->fresh()->vitalSign->nutrition_assessment_method)->toBe('Age-specific growth assessment');
});

it('stores and resumes incomplete drafts without creating diagnoses completed records or surveillance cases', function () {
    [$doctor, $queue, $form] = itrConsultation();
    $form->set('chief_complaint', 'Draft complaint')
        ->set('clinicalNotes.subjective_notes', 'Draft subjective narrative')
        ->set('physical_examination.skin.findings', ['Rashes'])
        ->set('selectedSymptomIds', [Symptom::where('name', 'Fever')->value('id')])
        ->call('saveDraft')->assertHasNoErrors()->assertSet('consultationVersion', 1);
    expect($queue->fresh()->status)->toBe('In Consultation')->and($queue->fresh()->completed_at)->toBeNull()
        ->and(MedicalRecord::count())->toBe(0)->and(Diagnosis::count())->toBe(0)->and(SurveillanceClassification::count())->toBe(0)
        ->and($queue->fresh()->toArray())->not->toHaveKey('consultation_draft');
    $resumed = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('resumeConsultation', $queue->queue_id)
        ->assertSet('chief_complaint', 'Draft complaint')->assertSet('clinicalNotes.subjective_notes', 'Draft subjective narrative')
        ->assertSet('physical_examination.skin.findings', ['Rashes'])
        ->set('diagnosis', 'Clinical wording')->set('treatment', 'Care instructions')->call('saveMedicalRecord')->assertHasNoErrors();
    expect($queue->fresh()->status)->toBe('Completed')->and($queue->fresh()->consultation_draft)->toBeNull()
        ->and($queue->fresh()->draft_saved_at)->toBeNull()->and($queue->fresh()->consultation_version)->toBe(2)
        ->and(MedicalRecord::count())->toBe(1)->and($queue->fresh()->medicalRecord->subjective_notes)->toBe('Draft subjective narrative');
    $resumed->call('saveDraft')->assertHasErrors('queueId');
});

it('rejects stale drafts and stale completion from another window', function () {
    [$doctor, $queue, $first] = itrConsultation();
    $second = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('resumeConsultation', $queue->queue_id);
    $first->set('chief_complaint', 'Latest draft')->call('saveDraft')->assertHasNoErrors();
    $second->set('chief_complaint', 'Stale changes')->call('saveDraft')->assertHasErrors('queue')
        ->set('diagnosis', 'Clinical wording')->set('treatment', 'Care instructions')
        ->call('saveMedicalRecord')->assertHasErrors('queue');
    expect($queue->fresh()->consultation_draft['chief_complaint'])->toBe('Latest draft')->and(MedicalRecord::count())->toBe(0);
});

it('validates physical findings against the official category and retains entries on failure', function () {
    [, $queue, $form] = itrConsultation();
    $form->set('diagnosis', 'Clinical wording')->set('treatment', 'Care instructions')
        ->set('physical_examination.heart.findings', ['Rashes'])->call('saveMedicalRecord')
        ->assertHasErrors('physical_examination.heart.findings.0')->assertSet('diagnosis', 'Clinical wording')
        ->set('physical_examination', [])->set('clinicalNotes.headss_home', 'Not an allowed consultation field')
        ->call('saveDraft')->assertHasErrors('clinicalNotes');
    expect(MedicalRecord::count())->toBe(0)->and($queue->fresh()->status)->toBe('In Consultation');
});

it('derives the visit date from the encounter and rejects a different submitted date', function () {
    [, $queue, $form] = itrConsultation();
    $form->set('diagnosis', 'Clinical wording')->set('treatment', 'Care instructions')
        ->set('consultation_date', now('Asia/Manila')->subDay()->toDateString())
        ->call('saveMedicalRecord')->assertHasErrors('consultation_date');
    expect(MedicalRecord::count())->toBe(0)->and($queue->fresh()->status)->toBe('In Consultation');
});

it('rolls back clinical changes if audit persistence fails', function (string $action) {
    [, $queue, $form] = itrConsultation();
    DB::statement("CREATE TRIGGER fail_clinical_audit BEFORE INSERT ON clinical_record_audits WHEN NEW.action = '".$action."' BEGIN SELECT RAISE(ABORT, 'Simulated audit failure'); END");
    try {
        $form->set('diagnosis', 'Clinical wording')->set('treatment', 'Care instructions')
            ->call($action === 'consultation-completed' ? 'saveMedicalRecord' : 'saveDraft')->assertHasErrors('queue');
        expect($queue->fresh()->status)->toBe('In Consultation')->and($queue->fresh()->consultation_draft)->toBeNull()
            ->and($queue->fresh()->consultation_version)->toBe(0)->and(MedicalRecord::count())->toBe(0)->and(Diagnosis::count())->toBe(0);
    } finally {
        DB::statement('DROP TRIGGER fail_clinical_audit');
    }
})->with(['consultation-completed', 'consultation-draft-saved']);

it('enforces draft authorization and doctor ownership on the backend', function () {
    [$doctor, $queue, $form] = itrConsultation();
    $otherDoctor = User::factory()->create(['role' => 'Doctor']);
    Livewire::actingAs($otherDoctor)->test(MedicalRecordsForm::class)->call('resumeConsultation', $queue->queue_id)
        ->assertHasErrors('queue')->assertSet('queueId', null);
    $doctor->forceFill(['role' => 'Local Health Unit Staff'])->save();
    Livewire::actingAs($doctor);
    $form->call('saveDraft')->assertForbidden();
    expect($queue->fresh()->consultation_draft)->toBeNull()->and(MedicalRecord::count())->toBe(0);
});

it('uses completed structured symptoms and retains clinical context without classifying physical or narrative findings', function () {
    [$doctor, $queue, $form] = itrConsultation();
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $rule = app(SurveillanceAdministration::class)->saveDefinition([
        'name' => 'SIMULATED ITR syndrome', 'description' => 'Test-only mapping', 'is_active' => true,
        'symptom_ids' => [Symptom::where('name', 'Fever')->value('id')], 'minimum_matches' => 1,
        'applies_from' => now('Asia/Manila')->subWeek()->toDateString(), 'rationale' => 'SIMULATED regression test definition.',
    ]);
    app(SurveillanceAdministration::class)->approve($rule->id);
    $queue->patient->update(['surveillance_residence' => 'Alabang']);
    Livewire::actingAs($doctor);
    $form->set('chief_complaint', 'Fever in own words')->set('clinicalNotes.assessment_notes', 'Clinical supporting assessment')
        ->set('clinicalNotes.subjective_notes', 'Fever mentioned in narrative')
        ->set('physical_examination.skin.findings', ['Rashes'])->set('diagnosis', 'Clinical wording')->set('treatment', 'Care instructions')
        ->call('selectSymptom', Symptom::where('name', 'Fever')->value('id'))
        ->call('saveDraft')->assertHasNoErrors();
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    expect(app(SurveillanceEngine::class)->run()->results->sole()->observed)->toBe(0);
    Livewire::actingAs($doctor);
    $form->call('saveMedicalRecord')->assertHasNoErrors();
    $classification = SurveillanceClassification::sole();
    expect($classification->matches)->toBeTrue()
        ->and($classification->evidence['chief_complaint'])->toBe('Fever in own words')
        ->and($classification->evidence['assessment_notes'])->toBe('Clinical supporting assessment');
    [, , $narrativeOnly] = itrConsultation();
    $narrativeOnly->set('chief_complaint', 'Fever')->set('clinicalNotes.subjective_notes', 'Fever')
        ->set('physical_examination.skin.findings', ['Rashes'])->set('diagnosis', 'Fever')->set('treatment', 'Care instructions')
        ->call('saveMedicalRecord')->assertHasNoErrors();
    expect(SurveillanceClassification::count())->toBe(1);
});

it('validates malformed legacy birthdates before using them for nutritional interpretation', function (mixed $birthdate) {
    Livewire::actingAs(User::factory()->create())->test(PatientVitalSignForm::class)
        ->set('birthdate', $birthdate)->call('save')->assertHasErrors('birthdate');
    expect(PatientInformation::count())->toBe(0)->and(VitalSign::count())->toBe(0);
})->with(['malformed string' => ['invalid-date'], 'malformed array' => [['invalid-date']]]);
