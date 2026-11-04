<?php

namespace App\Services;

use App\Models\FollowUp;
use App\Models\FollowUpMessage;
use App\Models\PatientInformation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FollowUpWorkflow
{
    public static function normalizeMobile(string $mobile): string
    {
        $number = preg_replace('/[\s()\-]+/', '', trim($mobile));
        if (preg_match('/^(?:0|\+63|63)?(9\d{9})$/D', $number, $matches)) {
            return '+63'.$matches[1];
        }
        throw ValidationException::withMessages(['mobile' => 'Enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).']);
    }

    /** @param array{patient_id: mixed, medical_record_id: mixed, date: string, time: string, purpose: string, internal_notes: ?string, mobile: string, sms_consent: bool} $data */
    public function save(array $data, ?int $id = null, ?int $version = null): FollowUp
    {
        Gate::authorize('follow-ups-manage');
        Validator::make($data, [
            'patient_id' => ['required', 'integer', 'exists:patient_information,patient_id'],
            'medical_record_id' => ['nullable', 'integer', 'exists:medical_records,medical_record_id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'purpose' => ['required', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'mobile' => ['required', 'string', 'max:30'],
            'sms_consent' => ['required', 'boolean'],
        ])->validate();
        $scheduledAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['time'], 'Asia/Manila')->utc();
        if ($scheduledAt->lessThanOrEqualTo(now('UTC'))) {
            throw ValidationException::withMessages(['date' => 'Choose a future appointment date and time in Asia/Manila.']);
        }
        $mobile = self::normalizeMobile($data['mobile']);

        return DB::transaction(function () use ($data, $id, $version, $scheduledAt, $mobile): FollowUp {
            $patient = PatientInformation::whereKey($data['patient_id'])->lockForUpdate()->firstOrFail();
            $appointment = $id ? FollowUp::whereKey($id)->lockForUpdate()->firstOrFail() : new FollowUp;
            if ($id && ($appointment->patient_id !== $patient->patient_id || $appointment->version !== $version || $appointment->status !== 'Scheduled')) {
                throw ValidationException::withMessages(['appointment' => 'This appointment changed. Reload it before editing.']);
            }
            $consultationId = filled($data['medical_record_id']) ? (int) $data['medical_record_id'] : null;
            if ($consultationId && ! $patient->medicalRecords()->whereKey($consultationId)->exists()) {
                throw ValidationException::withMessages(['medical_record_id' => 'Choose a consultation for this patient.']);
            }
            if ($id && $appointment->medical_record_id !== $consultationId) {
                throw ValidationException::withMessages(['medical_record_id' => 'The original consultation link must be preserved.']);
            }
            if (FollowUp::where('patient_id', $patient->patient_id)->where('status', 'Scheduled')->where('scheduled_at', $scheduledAt)->when($id, fn ($query) => $query->where('id', '!=', $id))->exists()
                || ($consultationId && FollowUp::where('medical_record_id', $consultationId)->when($id, fn ($query) => $query->where('id', '!=', $id))->exists())) {
                throw ValidationException::withMessages(['appointment' => 'An appointment already exists for this time or consultation. Open the existing appointment.']);
            }
            if ($id && $appointment->scheduled_at->equalTo($scheduledAt) && $appointment->purpose === $data['purpose']
                && ($appointment->internal_notes ?? '') === ($data['internal_notes'] ?? '') && $appointment->mobile === $mobile
                && $patient->sms_consent === $data['sms_consent'] && $patient->contact_number === $mobile) {
                return $appointment;
            }
            $notificationChanged = ! $id || ! $appointment->scheduled_at->equalTo($scheduledAt) || $appointment->mobile !== $mobile || $patient->sms_consent !== $data['sms_consent'] || $patient->contact_number !== $mobile;
            if ($id && $notificationChanged) {
                $this->cancelPending($appointment);
            }
            if (! $data['sms_consent']) {
                FollowUpMessage::whereIn('follow_up_id', $patient->followUps()->select('id'))->where('status', 'Pending')
                    ->update(['status' => 'Cancelled', 'outcome' => 'Patient opted out of SMS notifications.']);
            }
            $patient->forceFill(['sms_consent' => $data['sms_consent'], 'contact_number' => $mobile])->save();
            $appointment->fill([
                'patient_id' => $patient->patient_id, 'medical_record_id' => $consultationId,
                'scheduled_at' => $scheduledAt, 'purpose' => $data['purpose'], 'internal_notes' => $data['internal_notes'] ?: null,
                'mobile' => $mobile, 'version' => $id ? $appointment->version + 1 : 1,
                'notification_version' => $id ? $appointment->notification_version + (int) $notificationChanged : 1,
                'active_slot' => $patient->patient_id.':'.$scheduledAt->format('YmdHi'),
            ]);
            if (! $id) {
                $appointment->created_by = auth()->id();
                $appointment->status = 'Scheduled';
            }
            $appointment->save();
            $this->event($appointment, $id ? 'Updated / rescheduled' : 'Scheduled', $patient->sms_consent);
            if ($patient->sms_consent && $notificationChanged) {
                $this->message($appointment, $id ? 'Reschedule' : 'Confirmation', CarbonImmutable::now('UTC'));
                if ($scheduledAt->isAfter(CarbonImmutable::now('UTC')->addHours(24))) {
                    $this->message($appointment, 'Reminder', $scheduledAt->subHours(24));
                }
            }

            return $appointment;
        });
    }

    public function transition(int $id, int $version, string $status): void
    {
        Gate::authorize('follow-ups-manage');
        abort_unless(in_array($status, ['Cancelled', 'Completed', 'No Show'], true), 422);
        DB::transaction(function () use ($id, $version, $status): void {
            $patientId = FollowUp::findOrFail($id)->patient_id;
            $patient = PatientInformation::whereKey($patientId)->lockForUpdate()->firstOrFail();
            $appointment = FollowUp::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($appointment->version !== $version || $appointment->status !== 'Scheduled') {
                throw ValidationException::withMessages(['appointment' => 'This appointment changed. Reload it before continuing.']);
            }
            $this->cancelPending($appointment);
            $appointment->update(['status' => $status, 'version' => $version + 1, 'notification_version' => $appointment->notification_version + 1, 'active_slot' => null]);
            $this->event($appointment, $status, $patient->sms_consent);
            if ($status === 'Cancelled' && $patient->sms_consent) {
                $this->message($appointment, 'Cancellation', CarbonImmutable::now('UTC'));
            }
        });
    }

    public function remind(int $id, int $version): void
    {
        Gate::authorize('follow-ups-manage');
        DB::transaction(function () use ($id, $version): void {
            $patientId = FollowUp::findOrFail($id)->patient_id;
            $patient = PatientInformation::whereKey($patientId)->lockForUpdate()->firstOrFail();
            $appointment = FollowUp::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($appointment->version !== $version || $appointment->status !== 'Scheduled' || $appointment->scheduled_at->isPast() || ! $patient->sms_consent) {
                throw ValidationException::withMessages(['appointment' => 'Reminders require a current, future appointment and patient SMS consent.']);
            }
            $message = $appointment->messages()->where('version', $appointment->notification_version)->where('type', 'Reminder')->first();
            if ($message && ($message->status !== 'Pending' || $message->attempts > 0 || $message->due_at->lessThanOrEqualTo(now('UTC')))) {
                throw ValidationException::withMessages(['appointment' => 'A reminder has already been requested for this appointment version.']);
            }
            if ($message) {
                $message->update(['due_at' => now('UTC')]);
            } else {
                $this->message($appointment, 'Reminder', CarbonImmutable::now('UTC'));
            }
            $this->event($appointment, 'Manual reminder requested', true);
        });
    }

    public function retry(int $messageId): void
    {
        Gate::authorize('follow-ups-manage');
        DB::transaction(function () use ($messageId): void {
            $original = FollowUpMessage::findOrFail($messageId);
            PatientInformation::whereKey($original->followUp->patient_id)->lockForUpdate()->firstOrFail();
            $appointment = FollowUp::whereKey($original->follow_up_id)->lockForUpdate()->firstOrFail();
            $message = FollowUpMessage::whereKey($messageId)->lockForUpdate()->firstOrFail();
            if ($message->status !== 'Failed' || $message->batch_id || $message->attempts >= 3 || $message->version !== $appointment->notification_version) {
                throw ValidationException::withMessages(['appointment' => 'This message cannot safely be retried. Check notification history.']);
            }
            $message->update(['status' => 'Pending', 'due_at' => now('UTC'), 'outcome' => 'Retry requested after verification.']);
            $this->event($appointment, 'SMS retry requested', (bool) $appointment->patient->sms_consent);
        });
    }

    private function cancelPending(FollowUp $appointment): void
    {
        $appointment->messages()->where('status', 'Pending')->update(['status' => 'Cancelled', 'outcome' => 'Appointment changed; message superseded.']);
    }

    private function event(FollowUp $appointment, string $action, bool $consent): void
    {
        $appointment->events()->create(['actor_id' => auth()->id(), 'action' => $action, 'snapshot' => [...$appointment->only(['scheduled_at', 'purpose', 'internal_notes', 'mobile', 'status', 'version', 'medical_record_id']), 'sms_consent' => $consent]]);
    }

    private function message(FollowUp $appointment, string $type, CarbonImmutable $dueAt): void
    {
        $appointment->messages()->create([
            'version' => $appointment->notification_version, 'type' => $type, 'recipient' => $appointment->mobile,
            'body' => TextBee::body($type, $appointment->scheduled_at), 'due_at' => $dueAt,
        ]);
    }
}
