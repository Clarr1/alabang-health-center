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

it('uses staff assigned numbers and permits reuse on a different Manila date', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-09 16:05:00 UTC'));
    DB::table('queue_days')->insert(['queue_date' => '2026-10-10', 'last_number' => 500]);
    $first = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 27);
    $second = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 8);
    expect($first->queue_date->toDateString())->toBe('2026-10-10')
        ->and($first->queue_number)->toBe(27)->and($second->queue_number)->toBe(8)
        ->and($first->assigned_by)->toBe(auth()->id())
        ->and($first->assignedBy->user_id)->toBe(auth()->id())
        ->and(DB::table('queue_days')->value('last_number'))->toBe(500);
    $this->travelTo(new DateTimeImmutable('2026-10-10 16:05:00 UTC'));
    $next = $this->workflow->addPatient($first->patient_id, 27);
    expect($next->queue_number)->toBe(27)->and(Queue::count())->toBe(3);
});

it('rejects duplicate active visits and preserves cancelled history', function () {
    $patient = PatientInformation::factory()->create();
    $queue = $this->workflow->addPatient($patient->patient_id, 27);
    expect(fn () => $this->workflow->addPatient($patient->patient_id, 99))->toThrow(ValidationException::class);
    $this->workflow->transition($queue->queue_id, 'Waiting', 'Cancelled');
    expect(fn () => $this->workflow->addPatient($patient->patient_id, 27))->toThrow(ValidationException::class);
    $next = $this->workflow->addPatient($patient->patient_id, 99);
    expect($next->queue_number)->toBe(99)->and($patient->queues()->count())->toBe(2);
});

it('rejects numbers already assigned today including closed visits', function (string $status) {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 42);
    $queue->update(['status' => $status]);
    try {
        $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 42);
        $this->fail('A used number should not be assigned again.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('queueNumber')
            ->and($exception->errors()['queueNumber'][0])->toContain('already been assigned today');
    }
    expect(Queue::count())->toBe(1)->and($queue->fresh()->queue_number)->toBe(42);
})->with(['Waiting', 'Completed', 'Cancelled', 'No Show']);

it('accepts only a supplied positive whole queue number', function (mixed $number) {
    $patient = PatientInformation::factory()->create();
    expect(fn () => $this->workflow->addPatient($patient->patient_id, $number))->toThrow(ValidationException::class);
    expect(Queue::count())->toBe(0)->and(DB::table('queue_days')->count())->toBe(0);
})->with([
    'missing' => [null], 'blank' => [''], 'zero' => [0], 'negative' => [-1],
    'decimal' => [1.5], 'decimal text' => ['1.0'], 'scientific notation' => ['1e2'],
    'nonnumeric' => ['abc'], 'boolean' => [true], 'array' => [[42]],
    'outside column range' => ['4294967296'],
]);

it('enforces active patient uniqueness in the database even outside the workflow service', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 27);
    expect(fn () => Queue::create([
        'patient_id' => $queue->patient_id, 'queue_date' => $queue->queue_date,
        'queue_number' => 2, 'status' => 'Waiting',
    ]))->toThrow(QueryException::class);
});

it('enforces daily queue number uniqueness in the database', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 27);
    expect(fn () => Queue::create([
        'patient_id' => PatientInformation::factory()->create()->patient_id,
        'queue_date' => $queue->queue_date, 'queue_number' => $queue->queue_number, 'status' => 'Waiting',
    ]))->toThrow(QueryException::class);
});

it('rejects invalid transitions and repeated actions without changing the assigned number', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 27);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'Waiting', 'Completed'))->toThrow(ValidationException::class);
    $this->workflow->transition($queue->queue_id, 'Waiting', 'Called');
    expect($queue->fresh()->called_at)->not->toBeNull()->and($queue->fresh()->queue_number)->toBe(27);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'Waiting', 'Called'))->toThrow(ValidationException::class);
    $this->workflow->transition($queue->queue_id, 'Called', 'No Show');
    expect($queue->fresh()->status)->toBe('No Show')->and($queue->fresh()->queue_number)->toBe(27);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'No Show', 'Waiting'))->toThrow(ValidationException::class);
});

it('requires saved visit records before advancing the queue', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'Vital Signs', 'Ready for Consultation'))->toThrow(ValidationException::class);
    $queue->update(['status' => 'In Consultation']);
    expect(fn () => $this->workflow->transition($queue->queue_id, 'In Consultation', 'Completed'))->toThrow(ValidationException::class);
});

it('restricts patient deletion to preserve queue history', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 27);
    expect(fn () => $queue->patient->delete())->toThrow(QueryException::class);
});

it('preserves queue history if the assigning account is removed', function () {
    $queue = $this->workflow->addPatient(PatientInformation::factory()->create()->patient_id, 27);
    auth()->user()->delete();
    expect($queue->fresh()->assigned_by)->toBeNull()->and($queue->fresh()->queue_number)->toBe(27);
});
