<?php

namespace App\Jobs;

use App\Models\FollowUpMessage;
use App\Services\TextBee;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RefreshFollowUpDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $messageId) {}

    public function handle(TextBee $textBee): void
    {
        if (! $textBee->configured()) {
            return;
        }
        DB::transaction(function () use ($textBee): void {
            $message = FollowUpMessage::whereKey($this->messageId)->lockForUpdate()->first();
            if (! $message || $message->status !== 'Submitted' || ! $message->batch_id
                || $message->checked_at?->isAfter(now('UTC')->subMinutes(5))) {
                return;
            }
            $result = $textBee->delivery($message->device_id, $message->batch_id, $message->recipient);
            $message->update(['checked_at' => now('UTC'), ...($result ?? ['outcome' => 'Delivery check unavailable. Will check again; no resend.'])]);
        });
    }
}
