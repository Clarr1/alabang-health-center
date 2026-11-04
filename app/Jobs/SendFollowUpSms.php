<?php

namespace App\Jobs;

use App\Models\FollowUp;
use App\Models\FollowUpMessage;
use App\Models\PatientInformation;
use App\Services\FollowUpWorkflow;
use App\Services\TextBee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendFollowUpSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $messageId) {}

    public function handle(TextBee $textBee): void
    {
        if (! $textBee->configured()) {
            return;
        }
        $claimed = DB::transaction(function (): bool {
            $message = FollowUpMessage::whereKey($this->messageId)->lockForUpdate()->first();
            if (! $message || $message->status !== 'Pending' || $message->due_at->isFuture() || $message->attempts >= 3) {
                return false;
            }
            $message->update(['status' => 'Uncertain', 'attempts' => $message->attempts + 1, 'attempted_at' => now('UTC'), 'outcome' => 'Send claimed; outcome not yet verified.']);

            return true;
        });
        if (! $claimed) {
            return;
        }
        DB::transaction(function () use ($textBee): void {
            $original = FollowUpMessage::findOrFail($this->messageId);
            $patient = PatientInformation::whereKey($original->followUp->patient_id)->lockForUpdate()->firstOrFail();
            $appointment = FollowUp::whereKey($original->follow_up_id)->lockForUpdate()->firstOrFail();
            $message = FollowUpMessage::whereKey($this->messageId)->lockForUpdate()->firstOrFail();
            if ($message->status !== 'Uncertain') {
                return;
            }
            try {
                $currentMobile = FollowUpWorkflow::normalizeMobile($patient->contact_number ?? '');
            } catch (ValidationException) {
                $currentMobile = null;
            }
            $expectedStatus = $message->type === 'Cancellation' ? 'Cancelled' : 'Scheduled';
            if ($appointment->notification_version !== $message->version || $appointment->status !== $expectedStatus
                || ($expectedStatus === 'Scheduled' && $appointment->scheduled_at->isPast())
                || ! $patient->sms_consent || $currentMobile !== $message->recipient || $appointment->mobile !== $message->recipient) {
                $result = ['status' => 'Cancelled', 'outcome' => 'Suppressed: appointment, contact or SMS consent changed.'];
            } else {
                $result = $textBee->send($message->recipient, $message->body);
            }
            if ($result['status'] === 'Pending' && $message->attempts >= 2) {
                $result = ['status' => 'Failed', 'outcome' => 'TextBee rate limit persisted after controlled retry.'];
            }
            $history = $message->attempt_history ?? [];
            $history[] = ['at' => $message->attempted_at->toIso8601String(), 'status' => $result['status'], 'outcome' => $result['outcome']];
            $message->update([...$result, 'attempt_history' => $history, 'device_id' => config('textbee.device_id'), 'due_at' => now('UTC')->addMinutes(5)]);
        });
    }
}
