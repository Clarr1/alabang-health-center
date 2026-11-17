<?php

use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Services\QueueWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $this->workflow = app(QueueWorkflow::class);
});

it('automatically assigns consecutive numbers and resets at Manila midnight without changing history', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-10 15:59:59 UTC'));
    $first = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    $second = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    expect($first->queue_date->toDateString())->toBe('2026-10-10')
        ->and($first->queue_number)->toBe(1)->and($second->queue_number)->toBe(2)
        ->and($first->assigned_by)->toBe(auth()->id())
        ->and($first->assignedBy->user_id)->toBe(auth()->id())
        ->and(DB::table('queue_days')->value('last_number'))->toBe(2);
    $this->travelTo(new DateTimeImmutable('2026-10-10 16:00:00 UTC'));
    $next = $this->workflow->addPatient($first->patient_id);
    expect($next->queue_number)->toBe(1)->and($next->queue_date->toDateString())->toBe('2026-10-11')
        ->and($next->queue_id)->not->toBe($first->queue_id)->and(Queue::count())->toBe(3)
        ->and($first->fresh()->queue_number)->toBe(1)->and($first->fresh()->queue_date->toDateString())->toBe('2026-10-10')
        ->and(DB::table('queue_days')->where('queue_date', '2026-10-10')->value('last_number'))->toBe(2);
});

it('rejects duplicate active visits and preserves cancelled history', function () {
    $patient = PatientInformation::factory()->create();
    $queue = $this->workflow->addPatient($patient->patient_id);
    expect(fn () => $this->workflow->addPatient($patient->patient_id))->toThrow(ValidationException::class);
    $this->workflow->transition($queue->queue_id, 'Waiting', 'Cancelled');
    $next = $this->workflow->addPatient($patient->patient_id);
    expect($next->queue_number)->toBe(2)->and($patient->queues()->count())->toBe(2)
        ->and($queue->fresh()->status)->toBe('Cancelled');
});

it('never reuses numbers already assigned today including closed visits', function (string $status) {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    $queue->update(['status' => $status]);
    $next = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    expect(Queue::count())->toBe(2)->and($queue->fresh()->queue_number)->toBe(1)->and($next->queue_number)->toBe(2);
})->with(['Waiting', 'Completed', 'Cancelled', 'No Show']);

it('does not allocate numbers for invalid patients', function (int $patientId) {
    expect(fn () => $this->workflow->addPatient($patientId))->toThrow(ValidationException::class);
    expect(Queue::count())->toBe(0)->and(DB::table('queue_days')->count())->toBe(0);
})->with([
    'zero' => [0], 'negative' => [-1], 'missing patient' => [999999],
]);

it('enforces active patient uniqueness in the database even outside the workflow service', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    expect(fn () => Queue::create([
        'patient_id' => $queue->patient_id, 'queue_date' => $queue->queue_date,
        'queue_number' => 2, 'status' => 'Waiting',
    ]))->toThrow(QueryException::class);
});

it('continues above both historical numbers and the daily counter', function (int $lastNumber, int $existingNumber, int $expectedNumber) {
    $date = now('Asia/Manila')->toDateString();
    DB::table('queue_days')->insert(['queue_date' => $date, 'last_number' => $lastNumber]);
    $historical = Queue::factory()->create(['queue_number' => $existingNumber, 'status' => 'Completed']);
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    expect($queue->queue_number)->toBe($expectedNumber)->and($historical->fresh()->queue_number)->toBe($existingNumber)
        ->and(DB::table('queue_days')->where('queue_date', $date)->value('last_number'))->toBe($expectedNumber);
})->with([[0, 42, 43], [500, 42, 501]]);

it('rolls back the sequence when creating a visit fails', function () {
    $patient = PatientInformation::factory()->create();
    Queue::creating(function (): void {
        throw new QueryException('sqlite', 'insert', [], new RuntimeException('Simulated queue failure'));
    });
    try {
        expect(fn () => $this->workflow->addPatient($patient->patient_id))->toThrow(QueryException::class);
        expect(Queue::count())->toBe(0)->and(DB::table('queue_days')->count())->toBe(0);
    } finally {
        Queue::flushEventListeners();
    }
    expect($this->workflow->addPatient($patient->patient_id)->queue_number)->toBe(1);
});

it('rejects sequence exhaustion without changing existing records', function () {
    $date = now('Asia/Manila')->toDateString();
    DB::table('queue_days')->insert(['queue_date' => $date, 'last_number' => 4294967295]);
    expect(fn () => $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id))->toThrow(ValidationException::class);
    expect(Queue::count())->toBe(0)->and(DB::table('queue_days')->value('last_number'))->toBe(4294967295);
});

it('enforces daily queue number uniqueness in the database', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    expect(fn () => Queue::create([
        'patient_id' => PatientInformation::factory()->create()->patient_id,
        'queue_date' => $queue->queue_date, 'queue_number' => $queue->queue_number, 'status' => 'Waiting',
    ]))->toThrow(QueryException::class);
});

it('rejects invalid transitions and repeated actions without changing the assigned number', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'Waiting', 'Completed'))->toThrow(ValidationException::class);
    $this->workflow->transition($queue->queue_id, 'Waiting', 'Called');
    expect($queue->fresh()->called_at)->not->toBeNull()->and($queue->fresh()->queue_number)->toBe(1);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'Waiting', 'Called'))->toThrow(ValidationException::class);
    $this->workflow->transition($queue->queue_id, 'Called', 'No Show');
    expect($queue->fresh()->status)->toBe('No Show')->and($queue->fresh()->queue_number)->toBe(1);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'No Show', 'Waiting'))->toThrow(ValidationException::class);
});

it('requires saved visit records before advancing the queue', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'Vital Signs', 'Ready for Consultation'))->toThrow(ValidationException::class);
    $queue->update(['status' => 'In Consultation']);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'In Consultation', 'Completed'))->toThrow(ValidationException::class);
});

it('restricts patient deletion to preserve queue history', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    expect(fn () => $queue->patient->delete())->toThrow(QueryException::class);
});

it('preserves queue history if the assigning account is removed', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id);
    auth()->user()->delete();
    expect($queue->fresh()->assigned_by)->toBeNull()->and($queue->fresh()->queue_number)->toBe(1);
});
