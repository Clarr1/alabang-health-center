<?php

use App\Livewire\MedicalRecordsForm;
use App\Livewire\MedicalRecordsTable;
use App\Livewire\QueueDisplay;
use App\Models\MedicalRecord;
use App\Models\Queue;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\QueueWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function consultationVisit(User $doctor, int $number = 42): Queue
{
    $queue = Queue::factory()->create(['queue_number' => $number, 'status' => 'Ready for Consultation']);
    VitalSign::create([
        'queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id,
        'recorded_by' => $doctor->user_id, 'blood_pressure' => '120/80',
        'pulse_rate' => 72, 'respiratory_rate' => 16, 'recorded_at' => now(),
    ]);

    return $queue;
}

it('shows todays ready consultation queue in assigned number order and polls arrivals', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $last = consultationVisit($doctor, 100);
    $first = consultationVisit($doctor, 2);
    Queue::factory()->create(['queue_number' => 1, 'status' => 'Waiting']);
    Queue::factory()->create(['queue_number' => 3, 'status' => 'Ready for Consultation']);
    DB::table('queue_days')->insert(['queue_date' => now('Asia/Manila')->subDay()->toDateString(), 'last_number' => 0]);
    Queue::factory()->create(['queue_number' => 1, 'status' => 'Ready for Consultation', 'queue_date' => now('Asia/Manila')->subDay()->toDateString()]);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->assertSee('wire:poll.5s', false);
    expect($component->get('consultationQueue')->pluck('queue_id')->all())->toBe([$first->queue_id, $last->queue_id]);
    $arrival = consultationVisit($doctor, 10);
    $component->call('$refresh');
    expect($component->get('consultationQueue')->pluck('queue_id')->all())->toBe([$first->queue_id, $arrival->queue_id, $last->queue_id]);
});

it('completes consultation with this visits vital signs doctor and optional fields then clears the form', function (?string $followUp, ?string $prescription) {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    $oldVital = VitalSign::create(['patient_id' => $queue->patient_id, 'recorded_by' => $doctor->user_id, 'blood_pressure' => '110/70', 'recorded_at' => now()->addHour()]);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)
        ->call('startConsultation', $queue->queue_id)->assertHasNoErrors()
        ->assertSet('queueId', $queue->queue_id)->assertSet('patient_id', $queue->patient_id)
        ->assertSet('vital_signs_id', $queue->vitalSign->vital_id)->assertSee('120/80')->assertDontSee('110/70')
        ->assertSee('Chief Complaint')->assertSee('Treatment Plan')
        ->assertSee('wire:confirm', false)->assertSee('window.alert', false)
        ->set('diagnosis', 'Allergies')->set('treatment', 'Rest')->set('consultation_notes', 'Sneezing')
        ->set('prescription', $prescription)->set('follow_up_date', $followUp);

    expect($queue->fresh()->status)->toBe('In Consultation')->and($queue->fresh()->consulting_doctor_id)->toBe($doctor->user_id);
    Livewire::actingAs($doctor)->test(QueueDisplay::class)->assertViewHas('stages', fn (array $stages): bool => $stages['consultation']['serving'][0]['number'] === 42);
    $component->call('saveMedicalRecord')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('medical-record-saved')->assertDispatched('queue-updated')
        ->assertSet('patient', null)->assertSet('patient_id', null)->assertSet('queueId', null)
        ->assertSet('diagnosis', '')->assertSet('follow_up_date', '');
    $record = MedicalRecord::firstOrFail();
    expect($record->patient_id)->toBe($queue->patient_id)->and($record->vital_signs_id)->toBe($queue->vitalSign->vital_id)
        ->and($record->handled_by)->toBe($doctor->user_id)->and($record->queue_id)->toBe($queue->queue_id)
        ->and($record->prescription)->toBe($prescription)->and($record->follow_up_date?->toDateString())->toBe($followUp)
        ->and($queue->fresh()->status)->toBe('Completed')->and($queue->fresh()->queue_number)->toBe(42);
    $component->call('saveMedicalRecord')->assertHasErrors('patient_id');
    expect(MedicalRecord::count())->toBe(1)->and($component->get('consultationQueue'))->toHaveCount(0);
    Livewire::actingAs($doctor)->test(QueueDisplay::class)->assertViewHas('stages', fn (array $stages): bool => $stages['consultation']['serving'] === []);
    Livewire::actingAs($doctor)->test(MedicalRecordsTable::class, ['patient_id' => $queue->patient_id])
        ->assertSee('View Details');
    $this->get(route('medical-records.show', ['record' => $queue->patient_id]))
        ->assertOk()->assertSee('Sneezing')->assertSee('120/80')->assertSee('View original entry');
})->with([
    'optional fields empty' => [null, null],
    'optional fields provided' => ['2099-01-01', 'Cetirizine'],
]);

it('keeps consultation inputs on validation and database failures and supports retry', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $queue->queue_id)
        ->set('diagnosis', 'Allergies')->call('saveMedicalRecord')->assertHasErrors('treatment')->assertHasNoErrors('consultation_notes')
        ->assertSet('diagnosis', 'Allergies')->assertSet('queueId', $queue->queue_id)
        ->set('treatment', 'Rest')->set('consultation_notes', 'Sneezing')
        ->set('follow_up_date', '2000-01-01')->call('saveMedicalRecord')->assertHasErrors('follow_up_date');
    expect($queue->fresh()->status)->toBe('In Consultation')->and(MedicalRecord::count())->toBe(0);

    $workflow = Mockery::mock(QueueWorkflow::class)->makePartial();
    $workflow->shouldReceive('saveMedicalRecord')->once()->andThrow(new QueryException('sqlite', 'insert', [], new RuntimeException('Simulated database failure')));
    app()->instance(QueueWorkflow::class, $workflow);
    $component->set('follow_up_date', '')->call('saveMedicalRecord')->assertHasErrors('queue')
        ->assertSet('diagnosis', 'Allergies')->assertSet('treatment', 'Rest')->assertNotDispatched('medical-record-saved');
    expect($queue->fresh()->status)->toBe('In Consultation')->and(MedicalRecord::count())->toBe(0);
    app()->instance(QueueWorkflow::class, new QueueWorkflow);
    $component->call('saveMedicalRecord')->assertHasNoErrors();
    expect($queue->fresh()->status)->toBe('Completed');
});

it('rejects competing doctors stale statuses and unauthorized actions', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $other = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $queue->queue_id)->assertHasNoErrors();
    Livewire::actingAs($other)->test(MedicalRecordsForm::class)->call('startConsultation', $queue->queue_id)->assertHasErrors('queue')->assertSet('patient', null)
        ->call('resumeConsultation', $queue->queue_id)->assertHasErrors('queue')->assertSet('patient', null);
    expect($queue->fresh()->consulting_doctor_id)->toBe($doctor->user_id)->and($queue->fresh()->status)->toBe('In Consultation');
    Livewire::actingAs(User::factory()->create())->test(MedicalRecordsForm::class)
        ->assertForbidden();
    $this->actingAs($other);
    expect(fn () => app(QueueWorkflow::class)->saveMedicalRecord($queue->queue_id, $queue->patient_id, $queue->vitalSign->vital_id, fn () => throw new RuntimeException('Should never save')))->toThrow(ValidationException::class);
    expect(MedicalRecord::count())->toBe(0);
});

it('rejects old visits missing vital signs and switching during an active consultation', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    $missing = Queue::factory()->create(['status' => 'Ready for Consultation']);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)
        ->call('startConsultation', $missing->queue_id)->assertHasErrors('queue')->assertSet('patient', null);
    DB::table('queue_days')->insert(['queue_date' => now('Asia/Manila')->subDay()->toDateString(), 'last_number' => 0]);
    $queue->update(['queue_date' => now('Asia/Manila')->subDay()->toDateString()]);
    $component->call('startConsultation', $queue->queue_id)->assertHasErrors('queue')->assertSet('patient', null);
    $queue->update(['queue_date' => now('Asia/Manila')->toDateString()]);
    $component->call('startConsultation', $queue->queue_id)->assertHasNoErrors()->set('diagnosis', 'Keep this input')
        ->call('startConsultation', $missing->queue_id)->assertHasErrors('queue')->assertSet('diagnosis', 'Keep this input');
    $oldVital = VitalSign::create(['patient_id' => $queue->patient_id, 'recorded_by' => $doctor->user_id]);
    $component->call('selectVitalSign', $oldVital->vital_id)->assertHasErrors('vital_signs_id')->assertSet('vital_signs_id', $queue->vitalSign->vital_id);
});

it('rejects a duplicate save from a second tab and preserves its submitted inputs', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    $first = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $queue->queue_id);
    $second = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('resumeConsultation', $queue->queue_id);
    foreach ([$first, $second] as $component) {
        $component->set('diagnosis', 'Allergies')->set('treatment', 'Rest')->set('consultation_notes', 'Sneezing');
    }
    $first->call('saveMedicalRecord')->assertHasNoErrors();
    $second->call('saveMedicalRecord')->assertHasErrors('queue')->assertSet('diagnosis', 'Allergies')->assertSet('queueId', $queue->queue_id);
    expect(MedicalRecord::count())->toBe(1)->and($queue->fresh()->status)->toBe('Completed');
});

it('protects consultation URLs while allowing the owning doctor to resume after navigation', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $other = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    $url = route('queues.consultation', ['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id]);
    $this->actingAs($doctor)->get($url)->assertStatus(409);
    Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $queue->queue_id)->assertHasNoErrors();
    $this->actingAs($other)->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs($doctor)->get($url)->assertOk()->assertSee('Current Visit Vital Signs');
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)
        ->assertSee('Resume Consultation')->call('resumeConsultation', $queue->queue_id)->assertHasNoErrors()
        ->assertSet('patient_id', $queue->patient_id)->assertSet('vital_signs_id', $queue->vitalSign->vital_id);
    $queue->update(['status' => 'Cancelled']);
    $component->set('diagnosis', 'Allergies')->set('treatment', 'Rest')->set('consultation_notes', 'Sneezing')
        ->call('saveMedicalRecord')->assertHasErrors('queue')->assertSet('diagnosis', 'Allergies');
    expect(MedicalRecord::count())->toBe(0)->and($queue->fresh()->status)->toBe('Cancelled');
    $this->actingAs($doctor)->get($url)->assertStatus(409);
});

it('shows consultation controls based on the stored role rather than the account name', function () {
    $staff = User::factory()->create(['name' => 'Admin', 'role' => 'Local Health Unit Staff']);
    Livewire::actingAs($staff)->test(MedicalRecordsForm::class)
        ->assertForbidden();

    foreach (['Admin', 'Doctor'] as $role) {
        $user = User::factory()->create(['role' => $role]);
        Livewire::actingAs($user)->test(MedicalRecordsForm::class)
            ->assertSee('Doctor&#039;s Consultation Queue', false)->assertSee('No patients ready for consultation today.')
            ->assertDontSee('Your current role is');
    }
});

it('opens the renamed page without manual patient selection and redirects legacy bookmarks to its queue', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    $this->actingAs($doctor)->get(route('doctors-consultation.index'))->assertOk()
        ->assertSee('<title>Doctor&#039;s Consultation</title>', false)
        ->assertSee('Doctor&#039;s Consultation Queue', false)
        ->assertSee('Select a patient from the queue to start consultation.')
        ->assertDontSee('id="patient-search"', false)
        ->assertDontSee('wire:submit="saveMedicalRecord"', false);
    $this->get(route('medical-form.index', ['patient_id' => $queue->patient_id]))
        ->assertRedirect(route('doctors-consultation.index'));
    Livewire::actingAs($doctor)->test(MedicalRecordsForm::class, ['patient_id' => $queue->patient_id])
        ->assertSet('patient_id', null)->assertSet('patient', null)
        ->call('saveMedicalRecord')->assertHasErrors(['queueId', 'patient_id']);
    expect($queue->fresh()->status)->toBe('Ready for Consultation')->and(MedicalRecord::count())->toBe(0);
});

it('loads patient details and previous records alongside current visit vitals only after queue selection', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    $previousVital = VitalSign::create([
        'patient_id' => $queue->patient_id, 'recorded_by' => $doctor->user_id,
        'blood_pressure' => '110/70', 'recorded_at' => now()->subDay(),
    ]);
    $previousRecord = MedicalRecord::create([
        'patient_id' => $queue->patient_id, 'vital_signs_id' => $previousVital->vital_id,
        'handled_by' => $doctor->user_id, 'diagnosis' => 'Previous consultation diagnosis',
        'consultation_date' => now()->subDay(), 'created_at' => now(),
    ]);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)
        ->assertDontSee('Patient Details')->assertDontSee('Previous consultation diagnosis')
        ->call('startConsultation', $queue->queue_id)->assertHasNoErrors()
        ->assertSee($queue->patient->first_name)->assertSee($queue->patient->address)
        ->assertSee('120/80')->assertDontSee('110/70')
        ->assertSee('Previous consultation diagnosis')
        ->set('diagnosis', 'Keep current consultation input')
        ->call('viewMedicalRecord', $previousRecord->medical_record_id)
        ->assertSee('Medical Record Details')->assertSee('110/70')
        ->call('closeMedicalRecordModal')->assertSet('diagnosis', 'Keep current consultation input');
    expect($component->get('vitalSigns')->pluck('vital_id')->all())->toBe([$queue->vitalSign->vital_id]);
});

it('prevents changing the selected consultation patient through a client property update', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = consultationVisit($doctor);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)
        ->call('startConsultation', $queue->queue_id);
    expect(fn () => $component->set('patient_id', $queue->patient_id + 1))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect($queue->fresh()->status)->toBe('In Consultation')->and(MedicalRecord::count())->toBe(0);
});
