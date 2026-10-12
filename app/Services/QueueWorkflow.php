<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\VitalSign;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QueueWorkflow
{
    /** @return array<string, array<int, mixed>> */
    public function assignmentRules(string $date): array
    {
        return [
            'selectedPatientId' => ['required', 'integer', 'exists:patient_information,patient_id'],
            'queueNumber' => [
                'bail', 'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_int($value) && (! is_string($value) || preg_match('/^[0-9]+$/', $value) !== 1)) {
                        $fail('The assigned queue number must be a positive whole number.');
                    }
                },
                'integer', 'min:1', 'max:4294967295',
                Rule::unique('queues', 'queue_number')->where('queue_date', $date),
            ],
        ];
    }

    /** @return array<string, string> */
    public function assignmentMessages(): array
    {
        return [
            'selectedPatientId.required' => 'Select a patient before assigning a queue number.',
            'queueNumber.required' => 'Enter the queue number you are assigning.',
            'queueNumber.integer' => 'The assigned queue number must be a positive whole number.',
            'queueNumber.min' => 'The assigned queue number must be a positive whole number.',
            'queueNumber.unique' => 'This queue number has already been assigned today. Choose another number.',
        ];
    }

    public function addPatient(int $patientId, mixed $queueNumber, ?string $expectedDate = null): Queue
    {
        Gate::authorize('queue-register');
        $date = now('Asia/Manila')->toDateString();

        if ($expectedDate !== null && $expectedDate !== $date) {
            throw ValidationException::withMessages(['queueNumber' => 'The queue date has changed. Review today’s assigned numbers and confirm again.']);
        }

        try {
            return DB::transaction(function () use ($patientId, $queueNumber, $date): Queue {
                DB::table('queue_days')->upsert([['queue_date' => $date, 'last_number' => 0]], ['queue_date'], ['queue_date']);
                DB::table('queue_days')->where('queue_date', $date)->lockForUpdate()->first();

                $validated = Validator::make(
                    ['selectedPatientId' => $patientId, 'queueNumber' => $queueNumber],
                    $this->assignmentRules($date),
                    $this->assignmentMessages(),
                )->validate();

                PatientInformation::query()->whereKey($patientId)->lockForUpdate()->firstOrFail();

                if (Queue::where('patient_id', $patientId)->whereDate('queue_date', $date)->whereNotIn('status', Queue::TERMINAL_STATUSES)->exists()) {
                    throw ValidationException::withMessages(['queue' => 'This patient already has an active queue entry today.']);
                }

                return Queue::create([
                    'patient_id' => $patientId,
                    'queue_date' => $date,
                    'queue_number' => (int) $validated['queueNumber'],
                    'assigned_by' => Auth::id(),
                    'status' => 'Waiting',
                ]);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            if (Queue::whereDate('queue_date', $date)->where('queue_number', $queueNumber)->exists()) {
                throw ValidationException::withMessages(['queueNumber' => $this->assignmentMessages()['queueNumber.unique']]);
            }

            if (Queue::whereDate('queue_date', $date)->where('patient_id', $patientId)->whereNotIn('status', Queue::TERMINAL_STATUSES)->exists()) {
                throw ValidationException::withMessages(['queue' => 'This patient already has an active queue entry today.']);
            }

            throw $exception;
        }
    }

    public function transition(int $queueId, string $expectedStatus, string $nextStatus): Queue
    {
        Gate::authorize('queue-transition', $nextStatus);

        return DB::transaction(function () use ($queueId, $expectedStatus, $nextStatus): Queue {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queueId);
            $this->assertStatus($queue, $expectedStatus);

            if (! array_key_exists($nextStatus, $queue->actions())) {
                throw ValidationException::withMessages(['queue' => 'This status change is not allowed.']);
            }

            if ($nextStatus === 'Ready for Consultation' && ! $queue->vitalSign()->exists()) {
                throw ValidationException::withMessages(['queue' => 'Save vital signs before marking the patient ready for consultation.']);
            }

            if ($nextStatus === 'Completed' && ! $queue->medicalRecord()->exists()) {
                throw ValidationException::withMessages(['queue' => 'Save the medical record before completing this visit.']);
            }

            $queue->status = $nextStatus;

            if ($nextStatus === 'Called') {
                $queue->called_at = now();
            }

            if ($nextStatus === 'Completed') {
                $queue->completed_at = now();
            }

            $queue->save();

            return $queue;
        }, 5);
    }

    public function saveVitalSigns(int $queueId, int $patientId, Closure $save): VitalSign
    {
        Gate::authorize('queue-register');

        return DB::transaction(function () use ($queueId, $patientId, $save): VitalSign {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queueId);
            $this->assertPatient($queue, $patientId);
            $this->assertStatus($queue, 'Vital Signs');
            $record = $save($queue);
            $this->transition($queueId, 'Vital Signs', 'Ready for Consultation');

            return $record;
        }, 5);
    }

    public function saveMedicalRecord(int $queueId, int $patientId, int $vitalId, Closure $save): MedicalRecord
    {
        Gate::authorize('queue-consult');

        return DB::transaction(function () use ($queueId, $patientId, $vitalId, $save): MedicalRecord {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queueId);
            $this->assertPatient($queue, $patientId);
            $this->assertStatus($queue, 'In Consultation');

            if (! $queue->vitalSign()->whereKey($vitalId)->exists()) {
                throw ValidationException::withMessages(['vital_signs_id' => 'Use the vital signs recorded for this queue visit.']);
            }

            $record = $save($queue);
            $this->transition($queueId, 'In Consultation', 'Completed');

            return $record;
        }, 5);
    }

    private function assertStatus(Queue $queue, string $expectedStatus): void
    {
        if ($queue->status !== $expectedStatus) {
            throw ValidationException::withMessages(['queue' => 'This queue entry has changed. Refresh and check its current status.']);
        }
    }

    private function assertPatient(Queue $queue, int $patientId): void
    {
        if ((int) $queue->patient_id !== $patientId) {
            throw ValidationException::withMessages(['patient_id' => 'The selected patient does not match this queue visit.']);
        }
    }
}
