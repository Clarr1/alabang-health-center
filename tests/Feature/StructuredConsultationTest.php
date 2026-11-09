<?php

use App\Livewire\MedicalRecordsForm;
use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use App\Models\Queue;
use App\Models\Symptom;
use App\Models\User;
use Database\Factories\MedicalRecordFactory;
use Database\Factories\VitalSignFactory;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** @return array{0: User, 1: Queue, 2: Testable} */
function structuredConsultation(): array
{
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = Queue::factory()->create(['status' => 'Ready for Consultation']);
    VitalSignFactory::new()->create(['queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id, 'recorded_by' => $doctor->user_id]);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)
        ->call('startConsultation', $queue->queue_id)
        ->set('chief_complaint', "Fever since yesterday.\nPatient's own words.")
        ->set('diagnosis', 'Proposed diagnosis wording')
        ->set('treatment', 'Rest and hydration')->set('consultation_notes', 'Review if symptoms persist.');

    return [$doctor, $queue, $component];
}

it('saves multiple symptoms custom entries complaint and laboratory requests with the completed visit', function () {
    [$doctor, $queue, $component] = structuredConsultation();
    $fever = Symptom::where('name', 'Fever')->firstOrFail();
    $cough = Symptom::where('name', 'Cough')->firstOrFail();
    $component->call('selectSymptom', $fever->id)->call('selectSymptom', $cough->id)->call('selectSymptom', $fever->id)
        ->set('customSymptom', 'Pain behind the eyes')->call('addCustomSymptom')
        ->set('laboratory_requests', 'CBC')->call('saveMedicalRecord')->assertHasNoErrors()
        ->assertDispatched('medical-record-saved')->assertSet('selectedSymptomIds', [])->assertSet('customSymptoms', []);
    $record = MedicalRecord::with('symptoms')->sole();
    expect($record->symptomNames())->toBe(['Fever', 'Cough', 'Pain behind the eyes'])
        ->and($record->chief_complaint)->toBe("Fever since yesterday.\nPatient's own words.")
        ->and($record->laboratory_requests)->toBe('CBC')
        ->and(Symptom::count())->toBe(8)->and($queue->fresh()->status)->toBe('Completed');
    $this->actingAs($doctor)->get(route('medical-records.show', ['record' => $queue->patient_id]))->assertOk()
        ->assertSee('Pain behind the eyes')->assertSee('CBC')->assertSee('Fever since yesterday.');
});

it('removes badges before saving and rejects duplicate custom symptoms', function () {
    [, , $component] = structuredConsultation();
    $fever = Symptom::where('name', 'Fever')->firstOrFail();
    $component->call('selectSymptom', $fever->id)->call('removeSymptom', $fever->id)->assertSet('selectedSymptomIds', [])
        ->set('customSymptom', 'Dry eyes')->call('addCustomSymptom')->set('customSymptom', ' DRY   EYES ')
        ->call('addCustomSymptom')->assertHasErrors('customSymptom')->assertSet('customSymptoms', ['Dry eyes'])
        ->call('removeCustomSymptom', 0)->assertSet('customSymptoms', [])
        ->set('customSymptom', 'Fever')->call('addCustomSymptom')->call('selectSymptom', $fever->id)
        ->assertSet('customSymptoms', [])->set('customSymptom', '')->call('saveMedicalRecord')->assertHasNoErrors();
    expect(MedicalRecord::sole()->symptomNames())->toBe(['Fever']);
});

it('searches active catalogs and selects an existing primary diagnosis', function () {
    [, , $component] = structuredConsultation();
    $diagnosis = Diagnosis::factory()->create(['name' => 'Seasonal allergies']);
    Diagnosis::factory()->create(['name' => 'Hidden inactive allergy', 'is_active' => false]);
    Diagnosis::factory()->create(['name' => 'Unreviewed allergy', 'review_status' => 'pending']);
    $component->set('diagnosisSearch', 'allerg')->set('symptomSearch', 'Cou');
    expect($component->get('availableDiagnoses')->modelKeys())->toBe([$diagnosis->id]);
    expect($component->get('availableSymptoms')->pluck('name')->all())->toBe(['Cough']);
    $component->call('selectDiagnosis', (string) $diagnosis->id)->assertSet('diagnosis', 'Seasonal allergies')
        ->call('saveMedicalRecord')->assertHasNoErrors();
    expect(MedicalRecord::sole()->diagnosis_id)->toBe($diagnosis->id)->and(MedicalRecord::sole()->diagnosis)->toBe('Seasonal allergies');
});

it('validates existing custom entries before adding another symptom', function () {
    [, $queue, $component] = structuredConsultation();
    $component->set('customSymptoms', [['name' => 'Invalid entry']])->set('customSymptom', 'Dry eyes')
        ->call('addCustomSymptom')->assertHasErrors('customSymptoms.0')->assertSet('customSymptom', 'Dry eyes');
    expect(MedicalRecord::count())->toBe(0)->and($queue->fresh()->status)->toBe('In Consultation');
});

it('keeps custom wording and proposes it once without publishing it to the catalog', function () {
    [, , $component] = structuredConsultation();
    $component->call('selectDiagnosis', 'other')->set('diagnosis', 'Possible viral illness, review in 48 hours')
        ->call('saveMedicalRecord')->assertHasNoErrors();
    $record = MedicalRecord::sole();
    $proposal = Diagnosis::sole();
    expect($record->diagnosis)->toBe('Possible viral illness, review in 48 hours')
        ->and($proposal->review_status)->toBe('pending')->and($proposal->is_active)->toBeFalse();
    [, , $second] = structuredConsultation();
    $second->set('diagnosis', 'POSSIBLE  viral illness, review in 48 hours')->call('saveMedicalRecord')->assertHasNoErrors();
    expect(Diagnosis::count())->toBe(1)->and(MedicalRecord::count())->toBe(2)
        ->and(MedicalRecord::latest('medical_record_id')->first()->diagnosis)->toBe('POSSIBLE  viral illness, review in 48 hours');
});

it('preserves legacy medical records without structured fields', function () {
    $record = MedicalRecordFactory::new()->create(['diagnosis' => 'Original legacy diagnosis', 'consultation_notes' => 'Original combined chief complaint and notes']);
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $this->actingAs($doctor)->get(route('medical-records.show', ['record' => $record->patient_id]))->assertOk()
        ->assertSee('Original legacy diagnosis')->assertSee('Original combined chief complaint and notes')->assertSee('Not recorded');
    expect($record->fresh()->diagnosis_id)->toBeNull()->and($record->fresh()->chief_complaint)->toBeNull();
});

it('preserves inputs and queue state for invalid structured selections', function (string $property, mixed $value, string $error) {
    [, $queue, $component] = structuredConsultation();
    $component->set($property, $value)->call('saveMedicalRecord')->assertHasErrors($error)
        ->assertSet($property, $value);
    expect(MedicalRecord::count())->toBe(0)->and(Diagnosis::count())->toBe(0)->and($queue->fresh()->status)->toBe('In Consultation');
})->with([
    'missing symptom' => ['selectedSymptomIds', [99999], 'selectedSymptomIds.0'],
    'malformed symptom' => ['selectedSymptomIds', [['id' => 1]], 'selectedSymptomIds.0'],
    'repeated symptom' => ['selectedSymptomIds', [1, 1], 'selectedSymptomIds.0'],
    'duplicate custom' => ['customSymptoms', ['Dry eyes', 'dry eyes'], 'customSymptoms.0'],
    'blank custom' => ['customSymptoms', ['   '], 'customSymptoms.0'],
    'malformed custom' => ['customSymptoms', [['name' => 'Fever']], 'customSymptoms.0'],
    'unknown diagnosis' => ['diagnosisSelection', '99999', 'diagnosisSelection'],
    'long complaint' => ['chief_complaint', str_repeat('a', 10001), 'chief_complaint'],
]);

it('rejects a catalog diagnosis deactivated after selection', function () {
    [, $queue, $component] = structuredConsultation();
    $diagnosis = Diagnosis::factory()->create();
    $component->call('selectDiagnosis', (string) $diagnosis->id);
    $diagnosis->update(['is_active' => false]);
    $component->call('saveMedicalRecord')->assertHasErrors('diagnosisSelection');
    expect(MedicalRecord::count())->toBe(0)->and($queue->fresh()->status)->toBe('In Consultation');
});

it('preserves selected diagnosis wording and saved symptom names when catalogs are renamed', function () {
    [$doctor, $queue, $component] = structuredConsultation();
    $diagnosis = Diagnosis::factory()->create(['name' => 'Original diagnosis wording']);
    $fever = Symptom::where('name', 'Fever')->firstOrFail();
    $component->call('selectDiagnosis', (string) $diagnosis->id)->call('selectSymptom', $fever->id);
    $diagnosis->update(['name' => 'Updated catalog wording']);
    $component->call('saveMedicalRecord')->assertHasNoErrors();
    $fever->update(['name' => 'Updated fever wording', 'is_active' => false]);
    $record = MedicalRecord::sole();
    expect($record->diagnosis)->toBe('Original diagnosis wording')->and($record->symptomNames())->toBe(['Fever']);
    $this->actingAs($doctor)->get(route('medical-records.show', ['record' => $queue->patient_id]))->assertOk()
        ->assertSee('Original diagnosis wording')->assertSee('Fever')->assertDontSee('Updated catalog wording')->assertDontSee('Updated fever wording');
});

it('rolls back the record proposal and symptoms when pivot saving fails and allows retry', function () {
    [, $queue, $component] = structuredConsultation();
    $component->call('selectSymptom', Symptom::where('name', 'Fever')->value('id'));
    DB::statement("CREATE TRIGGER fail_symptom_save BEFORE INSERT ON medical_record_symptoms BEGIN SELECT RAISE(ABORT, 'Simulated pivot failure'); END");
    try {
        $component->call('saveMedicalRecord')->assertHasErrors('queue')->assertNotDispatched('medical-record-saved')
            ->assertSet('diagnosis', 'Proposed diagnosis wording');
        expect(MedicalRecord::count())->toBe(0)->and(Diagnosis::count())->toBe(0)
            ->and(DB::table('medical_record_symptoms')->count())->toBe(0)->and($queue->fresh()->status)->toBe('In Consultation');
    } finally {
        DB::statement('DROP TRIGGER fail_symptom_save');
    }
    $component->call('saveMedicalRecord')->assertHasNoErrors()->call('saveMedicalRecord')->assertHasErrors('queueId');
    expect(MedicalRecord::count())->toBe(1)->and(DB::table('medical_record_symptoms')->count())->toBe(1)
        ->and($queue->fresh()->status)->toBe('Completed');
});

it('rejects unauthorized clinical actions after a role changes', function () {
    [$doctor, $queue, $component] = structuredConsultation();
    $doctor->role = 'Local Health Unit Staff';
    $doctor->save();
    Livewire::actingAs($doctor);
    $component->call('saveMedicalRecord')->assertForbidden();
    expect(MedicalRecord::count())->toBe(0)->and($queue->fresh()->status)->toBe('In Consultation');
});
