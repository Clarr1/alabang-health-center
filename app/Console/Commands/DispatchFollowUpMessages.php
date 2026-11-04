<?php

namespace App\Console\Commands;

use App\Jobs\RefreshFollowUpDelivery;
use App\Jobs\SendFollowUpSms;
use App\Models\FollowUpMessage;
use App\Services\TextBee;
use Illuminate\Console\Command;

class DispatchFollowUpMessages extends Command
{
    protected $signature = 'follow-ups:dispatch';

    protected $description = 'Queue due follow-up notifications and refresh submitted SMS delivery reports';

    public function handle(): int
    {
        if (! app(TextBee::class)->configured()) {
            $this->info('SMS disabled or configuration incomplete. No messages queued.');

            return self::SUCCESS;
        }
        FollowUpMessage::where('status', 'Pending')->where('due_at', '<=', now('UTC'))->where('attempts', '<', 3)
            ->chunkById(100, function ($messages): void {
                foreach ($messages as $message) {
                    SendFollowUpSms::dispatch($message->id);
                }
            });
        FollowUpMessage::where('status', 'Submitted')->whereNotNull('batch_id')
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<=', now('UTC')->subMinutes(5)))
            ->chunkById(100, function ($messages): void {
                foreach ($messages as $message) {
                    RefreshFollowUpDelivery::dispatch($message->id);
                }
            });

        return self::SUCCESS;
    }
}
