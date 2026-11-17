<?php

use App\Livewire\MedicalRecordsForm;
use App\Livewire\PatientInformationForm;
use App\Livewire\QueueManagement;
use App\Livewire\VitalSignsForm;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\QueueWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('runs registration through vital signs consultation and completed medical record', function () {
    $staff = User::factory()->create();
    $doctor = User::factory()->create(['role' => 'Doctor']);
    Livewire::actingAs($staff)->test(PatientInformationForm::class)
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set('first_name', 'Maria')->set('last_name', 'Santos')
        ->set('birthdate', '1990-01-01')->set('sex', 'Female')
        ->set('address', 'Alabang')->call('savePatient')->assertHasNoErrors()
        ->assertDispatched('patient-information-saved');
    $patient = PatientInformation::firstOrFail();
    expect(Queue::count())->toBe(0);
    Livewire::actingAs($staff)->test(QueueManagement::class)
        ->call('selectPatient', $patient->patient_id)->call('addPatient')->assertHasNoErrors()
        ->assertSee('Assigned queue number: 001');
    $queue = Queue::firstOrFail();
    expect($queue->status)->toBe('Waiting')->and($queue->queue_number)->toBe(1)->and($queue->assigned_by)->toBe($staff->user_id);
    $workflow = app(QueueWorkflow::class);
    $workflow->transition($queue->queue_id, 'Waiting', 'Called');
    $workflow->transition($queue->queue_id, 'Called', 'Vital Signs');

    $vitals = Livewire::actingAs($staff)->test(VitalSignsForm::class, [
        'patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id,
    ])->assertSee('Queue 001')->set('blood_pressure', '120/80')->set('height', 160)->set('weight', 55)
        ->call('saveVitalSigns')->assertHasNoErrors()->assertSet('queueId', null)->assertSet('patient_id', null)->assertDispatched('vital-signs-saved');
    expect($queue->fresh()->status)->toBe('Ready for Consultation')
        ->and($queue->fresh()->queue_number)->toBe(1)
        ->and($queue->vitalSign->bmi)->toEqual(21.5)
        ->and($queue->vitalSign->patient->patient_id)->toBe($queue->patient_id);
    $vitals->call('saveVitalSigns')->assertHasErrors('patient_id');
    expect(VitalSign::count())->toBe(1);

    $this->actingAs($doctor);
    $workflow->transition($queue->queue_id, 'Ready for Consultation', 'In Consultation');
    $medical = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class, [
        'patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id,
    ])->assertSee('Queue 001')->assertSet('vital_signs_id', $queue->vitalSign->vital_id)->assertSee('120/80')
        ->set('diagnosis', 'Seasonal allergies')->set('prescription', 'Cetirizine')->set('treatment', 'Rest')->set('consultation_notes', 'Sneezing')
        ->call('saveMedicalRecord')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('medical-record-saved');
    expect($queue->fresh()->status)->toBe('Completed')
        ->and($queue->fresh()->queue_number)->toBe(1)
        ->and($queue->fresh()->completed_at)->not->toBeNull()
        ->and($queue->medicalRecord->patient_id)->toBe($queue->patient_id);
    $medical->call('saveMedicalRecord')->assertHasErrors('patient_id');
    expect(MedicalRecord::count())->toBe(1);
});

it('keeps queue status unchanged when vital signs validation fails', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    Livewire::actingAs(User::factory()->create())->test(VitalSignsForm::class, [
        'patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id,
    ])->set('temperature', 99)->call('saveVitalSigns')->assertHasErrors('temperature');
    expect($queue->fresh()->status)->toBe('Vital Signs')->and(VitalSign::count())->toBe(0);
});

it('rolls back the record and queue when a database save fails', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    $this->actingAs(User::factory()->create());
    expect(fn () => app(QueueWorkflow::class)->saveVitalSigns(
        $queue->queue_id, $queue->patient_id, function (Queue $visit): VitalSign {
            VitalSign::create(['queue_id' => $visit->queue_id, 'patient_id' => $visit->patient_id, 'recorded_by' => auth()->id()]);
            DB::table('nonexistent_visit_table')->insert(['value' => 1]);

            return $visit->vitalSign;
        },
    ))->toThrow(QueryException::class);
    expect($queue->fresh()->status)->toBe('Vital Signs')->and(VitalSign::count())->toBe(0);
});

it('rejects a mismatched patient and stale cancelled visit', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    $otherPatient = PatientInformation::factory()->create();
    $component = Livewire::actingAs(User::factory()->create())->test(VitalSignsForm::class, [
        'patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id,
    ]);
    $component->set('patient_id', $otherPatient->patient_id)->call('saveVitalSigns')->assertHasErrors('patient_id');
    $queue->update(['status' => 'Cancelled']);
    $component->set('patient_id', $queue->patient_id)->call('saveVitalSigns')->assertHasErrors('queue');
    expect(VitalSign::count())->toBe(0);
});

it('rejects medical records that use another visits vital signs and preserves consultation on validation failure', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = Queue::factory()->create(['status' => 'In Consultation']);
    $vital = VitalSign::create(['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id, 'recorded_by' => $doctor->user_id, 'recorded_at' => now()]);
    $oldVital = VitalSign::create(['patient_id' => $queue->patient_id, 'recorded_by' => $doctor->user_id, 'recorded_at' => now()->subDay()]);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class, [
        'patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id,
    ])->call('saveMedicalRecord')->assertHasErrors('diagnosis');
    $component->set('diagnosis', 'Allergies')->set('treatment', 'Rest')->set('consultation_notes', 'Sneezing')->set('vital_signs_id', $oldVital->vital_id)
        ->call('saveMedicalRecord')->assertHasErrors('vital_signs_id');
    expect($queue->fresh()->status)->toBe('In Consultation')->and(MedicalRecord::count())->toBe(0);
});

it('does not complete consultation when its medical record transaction fails', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $this->actingAs($doctor);
    $queue = Queue::factory()->create(['status' => 'In Consultation']);
    $vital = VitalSign::create(['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id, 'recorded_by' => $doctor->user_id]);
    expect(fn () => app(QueueWorkflow::class)->saveMedicalRecord(
        $queue->queue_id, $queue->patient_id, $vital->vital_id, function (Queue $visit) use ($vital, $doctor): MedicalRecord {
            MedicalRecord::create([
                'queue_id' => $visit->queue_id, 'patient_id' => $visit->patient_id, 'vital_signs_id' => $vital->vital_id,
                'handled_by' => $doctor->user_id, 'diagnosis' => 'Allergies', 'consultation_date' => now(), 'created_at' => now(),
            ]);
            DB::table('nonexistent_visit_table')->insert(['value' => 1]);

            return $visit->medicalRecord;
        },
    ))->toThrow(QueryException::class);
    expect($queue->fresh()->status)->toBe('In Consultation')->and($queue->fresh()->completed_at)->toBeNull()
        ->and(MedicalRecord::count())->toBe(0);
});

it('prevents staff from saving consultation records and doctors from saving vital signs', function () {
    Livewire::actingAs(User::factory()->create())->test(MedicalRecordsForm::class)->assertForbidden();
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(VitalSignsForm::class)->assertForbidden();
});

it('detects active queues on the original form routes and prevents skipping workflow steps', function () {
    $queue = Queue::factory()->create();
    Livewire::actingAs(User::factory()->create())->test(VitalSignsForm::class, ['patient_id' => $queue->patient_id])
        ->call('saveVitalSigns')->assertHasErrors('queue');
    expect(VitalSign::count())->toBe(0)->and($queue->fresh()->status)->toBe('Waiting');
});

it('shows only todays vital signs queue in numeric order and refreshes new arrivals', function () {
    $first = Queue::factory()->create(['queue_number' => 2]);
    $last = Queue::factory()->create(['queue_number' => 100, 'status' => 'Called']);
    Queue::factory()->create(['queue_number' => 1, 'status' => 'Ready for Consultation']);
    DB::table('queue_days')->insert(['queue_date' => now('Asia/Manila')->subDay()->toDateString(), 'last_number' => 0]);
    Queue::factory()->create(['queue_date' => now('Asia/Manila')->subDay()->toDateString()]);
    $component = Livewire::actingAs(User::factory()->create())->test(VitalSignsForm::class)
        ->assertSee('wire:poll.5s', false);
    expect($component->get('vitalSignsQueue')->pluck('queue_id')->all())->toBe([$first->queue_id, $last->queue_id]);
    $arrival = Queue::factory()->create(['queue_number' => 10, 'status' => 'Vital Signs']);
    $component->call('$refresh');
    expect($component->get('vitalSignsQueue')->pluck('queue_id')->all())->toBe([$first->queue_id, $arrival->queue_id, $last->queue_id]);
});

it('selects a queue patient and sends the same visit to the doctors queue after saving', function (string $status) {
    $queue = Queue::factory()->create(['queue_number' => 42, 'status' => $status]);
    $staff = User::factory()->create();
    $component = Livewire::actingAs($staff)->test(VitalSignsForm::class)
        ->call('selectQueue', $queue->queue_id, $status)->assertHasNoErrors()
        ->assertSet('patient_id', $queue->patient_id)->assertSet('queueId', $queue->queue_id)
        ->assertSee($queue->patient->first_name)->assertSee('Save Vital Signs and Send to Consultation')
        ->set('height', 160)->set('weight', 55)->set('temperature', 99)
        ->call('saveVitalSigns')->assertHasErrors('temperature');
    expect($queue->fresh()->status)->toBe('Vital Signs')->and(VitalSign::count())->toBe(0);
    $component->set('temperature', 36.5)->set('blood_pressure', '120/80')
        ->call('saveVitalSigns')->assertHasNoErrors()->assertNoRedirect()
        ->assertSet('patient_id', null)->assertSet('patient', null)->assertSet('queueId', null)
        ->assertSet('height', '')->assertSet('weight', '')->assertSet('bmi', '')
        ->assertSee('ready for consultation');
    expect($queue->fresh()->status)->toBe('Ready for Consultation')
        ->and($queue->fresh()->queue_number)->toBe(42)->and(Queue::count())->toBe(1)
        ->and($queue->vitalSign->patient_id)->toBe($queue->patient_id)
        ->and($queue->vitalSign->recorded_by)->toBe($staff->user_id)
        ->and($queue->vitalSign->bmi)->toEqual(21.5);
    $component->call('saveVitalSigns')->assertHasErrors('patient_id');
    expect(VitalSign::count())->toBe(1)->and($component->get('vitalSignsQueue'))->toHaveCount(0);
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(QueueManagement::class)
        ->set('status', 'Ready for Consultation')->assertSee($queue->patient->first_name)
        ->assertSee('Start Consultation');
})->with(['Waiting', 'Called', 'Vital Signs']);

it('rejects stale ineligible and previous day queue selections without changing the form', function () {
    $queue = Queue::factory()->create(['status' => 'Called']);
    $component = Livewire::actingAs(User::factory()->create())->test(VitalSignsForm::class)
        ->call('selectQueue', $queue->queue_id, 'Waiting')->assertHasErrors('queue')->assertSet('queueId', null);
    expect($queue->fresh()->status)->toBe('Called');
    $queue->update(['status' => 'Ready for Consultation']);
    $component->call('selectQueue', $queue->queue_id, 'Ready for Consultation')->assertHasErrors('queue');
    DB::table('queue_days')->insert(['queue_date' => now('Asia/Manila')->subDay()->toDateString(), 'last_number' => 0]);
    $queue->update(['status' => 'Waiting', 'queue_date' => now('Asia/Manila')->subDay()->toDateString()]);
    $component->call('selectQueue', $queue->queue_id, 'Waiting')->assertHasErrors('queue')->assertSet('patient', null);
    expect($queue->fresh()->status)->toBe('Waiting')->and(VitalSign::count())->toBe(0);
});

it('rejects duplicate saves from another staff form for the same visit', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    $staff = User::factory()->create();
    $first = Livewire::actingAs($staff)->test(VitalSignsForm::class)->call('selectQueue', $queue->queue_id, 'Vital Signs');
    $second = Livewire::actingAs($staff)->test(VitalSignsForm::class)->call('selectQueue', $queue->queue_id, 'Vital Signs');
    $first->call('saveVitalSigns')->assertHasNoErrors();
    $second->call('saveVitalSigns')->assertHasErrors('queue');
    expect(VitalSign::count())->toBe(1)->and(Queue::count())->toBe(1)
        ->and($queue->fresh()->status)->toBe('Ready for Consultation');
});
