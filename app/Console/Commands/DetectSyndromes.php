<?php

namespace App\Console\Commands;

use App\Models\SurveillanceRun;
use App\Models\SurveillanceSetting;
use App\Services\SurveillanceEngine;
use Illuminate\Console\Command;
use Throwable;

class DetectSyndromes extends Command
{
    protected $signature = 'surveillance:detect {--scheduled : Respect the configured automatic detection interval}';

    protected $description = 'Evaluate eligible Barangay Alabang encounters and store internal surveillance signals';

    public function handle(SurveillanceEngine $engine): int
    {
        if ($this->option('scheduled')) {
            $last = SurveillanceRun::whereIn('status', ['Running', 'Completed'])->latest('id')->first();
            if ($last && $last->created_at->gt(now()->subMinutes((int) SurveillanceSetting::current()['frequency_minutes']))) {
                return self::SUCCESS;
            }
        }
        try {
            $run = $engine->run($this->option('scheduled') ? 'scheduled' : 'console');
            $this->info("Detection run {$run->id}: {$run->status}. All alerts are internal development signals.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Surveillance detection failed. See the execution log and application log.');

            return self::FAILURE;
        }
    }
}
