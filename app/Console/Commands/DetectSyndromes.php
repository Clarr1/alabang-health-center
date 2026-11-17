<?php

namespace App\Console\Commands;

use App\Models\SurveillanceRun;
use App\Models\SurveillanceSetting;
use App\Services\SurveillanceEngine;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class DetectSyndromes extends Command
{
    protected $signature = 'surveillance:detect {--scheduled : Respect the configured automatic detection interval} {--at= : Replay a historical window ending at YYYY-MM-DDTHH:MM:SSZ (UTC)}';

    protected $description = 'Evaluate eligible Barangay Alabang encounters and store internal surveillance signals';

    public function handle(SurveillanceEngine $engine): int
    {
        $end = null;
        if ($this->option('at') !== null) {
            try {
                $value = (string) $this->option('at');
                $end = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC');
                if ($this->option('scheduled') || $end->format('Y-m-d\TH:i:s\Z') !== $value || $end->isFuture()) {
                    throw new \InvalidArgumentException;
                }
            } catch (Throwable) {
                $this->error('Use --at=YYYY-MM-DDTHH:MM:SSZ with a past UTC date, without --scheduled.');

                return self::FAILURE;
            }
        }
        if ($this->option('scheduled')) {
            $last = SurveillanceRun::whereIn('status', ['Running', 'Completed'])->latest('id')->first();
            if ($last && $last->created_at->gt(now()->subMinutes((int) SurveillanceSetting::current()['frequency_minutes']))) {
                return self::SUCCESS;
            }
        }
        try {
            $run = $engine->run($this->option('scheduled') ? 'scheduled' : 'console', $end);
            $this->info("Detection run {$run->id}: {$run->status}. All alerts are internal development signals.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Surveillance detection failed. See the execution log and application log.');

            return self::FAILURE;
        }
    }
}
