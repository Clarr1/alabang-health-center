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
use Illuminate\Validation\ValidationException;

class QueueWorkflow
{
    /** @return array<string, array<int, mixed>> */
    public function assignmentRules(): array
    {
        return [
            'selectedPatientId' => ['required', 'integer', 'exists:patient_information,patient_id'],
        ];
    }

    /** @return array<string, string> */
    public function assignmentMessages(): array
    {
        return [
            'selectedPatientId.required' => 'Select a patient before adding them to the queue.',
        ];
    }

    public function addPatient(int $patientId): Queue
    {
        Gate::authorize('queue-register');
        $date = now('Asia/Manila')->toDateString();

        try {
            return DB::transaction(function () use ($patientId, $date): Queue {
                DB::table('queue_days')->upsert([['queue_date' => $date, 'last_number' => 0]], ['queue_date'], ['queue_date']);
                $queueDay = DB::table('queue_days')->where('queue_date', $date)->lockForUpdate()->first();

                Validator::make(
                    ['selectedPatientId' => $patientId],
                    $this->assignmentRules(),
                    $this->assignmentMessages(),
                )->validate();

                PatientInformation::query()->whereKey($patientId)->lockForUpdate()->firstOrFail();

                if (Queue::where('patient_id', $patientId)->whereDate('queue_date', $date)->whereNotIn('status', Queue::TERMINAL_STATUSES)->exists()) {
                    throw ValidationException::withMessages(['queue' => 'This patient already has an active queue entry today.']);
                }

                $queueNumber = max((int) $queueDay->last_number, (int) Queue::where('queue_date', $date)->max('queue_number')) + 1;
                if ($queueNumber > 4294967295) {
                    throw ValidationException::withMessages(['queue' => 'The daily queue number limit has been reached.']);
                }
                DB::table('queue_days')->where('queue_date', $date)->update(['last_number' => $queueNumber]);

                return Queue::create([
                    'patient_id' => $patientId,
                    'queue_date' => $date,
                    'queue_number' => $queueNumber,
                    'assigned_by' => Auth::id(),
                    'status' => 'Waiting',
                ]);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
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

            if ($nextStatus === 'Ready for Consultation' && ! $queue->vitalSign()->where('patient_id', $queue->patient_id)->exists()) {
                throw ValidationException::withMessages(['queue' => 'Save vital signs before marking the patient ready for consultation.']);
            }

            if ($nextStatus === 'Completed' && ! $queue->medicalRecord()->where('patient_id', $queue->patient_id)
                ->whereIn('vital_signs_id', $queue->vitalSign()->where('patient_id', $queue->patient_id)->select('vital_id'))->exists()) {
                throw ValidationException::withMessages(['queue' => 'Save the medical record before completing this visit.']);
            }

            if ($nextStatus === 'In Consultation') {
                if ($queue->queue_date->toDateString() !== now('Asia/Manila')->toDateString() || ! $queue->vitalSign()->where('patient_id', $queue->patient_id)->exists()) {
                    throw ValidationException::withMessages(['queue' => 'Select a current visit with recorded vital signs.']);
                }
                $queue->consulting_doctor_id = Auth::id();
            }

            if ($nextStatus === 'Completed') {
                $this->assertConsultingDoctor($queue);
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
            if ($queue->queue_date->toDateString() !== now('Asia/Manila')->toDateString() || $queue->vitalSign()->exists()) {
                throw ValidationException::withMessages(['queue' => 'Select the current unsaved vital signs visit.']);
            }
            $record = $save($queue);
            IndividualTreatmentRecord::audit($patientId, $queueId, 'vital-signs-recorded', $record->toArray());
            $this->transition($queueId, 'Vital Signs', 'Ready for Consultation');

            return $record;
        }, 5);
    }

    public function saveMedicalRecord(int $queueId, int $patientId, int $vitalId, Closure $save, ?int $version = null): MedicalRecord
    {
        Gate::authorize('queue-consult');

        return DB::transaction(function () use ($queueId, $patientId, $vitalId, $save, $version): MedicalRecord {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queueId);
            $this->assertPatient($queue, $patientId);
            $this->assertStatus($queue, 'In Consultation');
            $this->assertConsultingDoctor($queue);
            if ($queue->queue_date->toDateString() !== now('Asia/Manila')->toDateString() || $queue->medicalRecord()->exists()) {
                throw ValidationException::withMessages(['queue' => 'Select the current unsaved consultation visit.']);
            }

            if (! $queue->vitalSign()->where('patient_id', $patientId)->whereKey($vitalId)->exists()) {
                throw ValidationException::withMessages(['vital_signs_id' => 'Use the vital signs recorded for this queue visit.']);
            }

            $this->assertConsultationVersion($queue, $version);
            $record = $save($queue);
            $record->update(['consultation_date' => $queue->queue_date, 'encounter_at' => $queue->created_at]);
            $queue->forceFill(['consultation_draft' => null, 'draft_saved_at' => null, 'consultation_version' => $queue->consultation_version + 1])->save();
            IndividualTreatmentRecord::audit($patientId, $queueId, 'consultation-completed', $record->load('symptoms')->toArray());
            $this->transition($queueId, 'In Consultation', 'Completed');
            app(SyndromeClassifier::class)->classify($record->load('queue'));

            return $record;
        }, 5);
    }

    /** @param array<string, mixed> $draft */
    public function saveConsultationDraft(int $queueId, int $patientId, int $vitalId, int $version, array $draft): Queue
    {
        Gate::authorize('queue-consult');

        return DB::transaction(function () use ($queueId, $patientId, $vitalId, $version, $draft): Queue {
            $queue = $this->resumeConsultation($queueId);
            $this->assertPatient($queue, $patientId);
            $this->assertConsultationVersion($queue, $version);
            if ($queue->medicalRecord()->exists() || ! $queue->vitalSign()->where('patient_id', $patientId)->whereKey($vitalId)->exists()) {
                throw ValidationException::withMessages(['queue' => 'Select the current unsaved consultation and its vital signs.']);
            }
            $queue->forceFill(['consultation_draft' => $draft, 'draft_saved_at' => now(), 'consultation_version' => $version + 1])->save();
            IndividualTreatmentRecord::audit($patientId, $queueId, 'consultation-draft-saved', ['version' => $version + 1, 'draft' => $draft]);

            return $queue;
        }, 5);
    }

    private function assertConsultationVersion(Queue $queue, ?int $version): void
    {
        if ($version !== null && $queue->consultation_version !== $version) {
            throw ValidationException::withMessages(['queue' => 'This consultation was saved in another window. Reopen it before saving again.']);
        }
    }

    public function resumeConsultation(int $queueId): Queue
    {
        Gate::authorize('queue-consult');

        return DB::transaction(function () use ($queueId): Queue {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queueId);
            $this->assertStatus($queue, 'In Consultation');
            $this->assertConsultingDoctor($queue);
            if ($queue->queue_date->toDateString() !== now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['queue' => 'Select a consultation from today.']);
            }

            return $queue;
        }, 5);
    }

    private function assertConsultingDoctor(Queue $queue): void
    {
        if ($queue->consulting_doctor_id === null) {
            $queue->consulting_doctor_id = Auth::id();
            $queue->save();
        }

        if ((int) $queue->consulting_doctor_id !== (int) Auth::id()) {
            throw ValidationException::withMessages(['queue' => 'This consultation belongs to another doctor.']);
        }
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
