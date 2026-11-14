<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\SurveillanceAlert;
use App\Models\SurveillanceClassification;
use App\Models\SurveillanceResult;
use App\Models\SurveillanceRun;
use App\Models\SurveillanceSetting;
use App\Models\SyndromeCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SurveillanceEngine
{
    public function __construct(private SyndromeClassifier $classifier, private SurveillanceStatistics $statistics) {}

    public function run(string $source = 'manual', ?CarbonImmutable $end = null): SurveillanceRun
    {
        if ($source === 'manual') {
            Gate::authorize('surveillance-manage');
        }
        abort_unless(in_array($source, ['manual', 'scheduled', 'console'], true), 422);
        $settings = SurveillanceSetting::current();
        $end = ($end ?? CarbonImmutable::now('UTC'))->utc()->startOfMinute();
        $start = $end->subHours((int) $settings['window_hours']);
        $run = SurveillanceRun::create([
            'source' => $source, 'triggered_by' => $source === 'manual' ? auth()->id() : null,
            'window_start' => $start, 'window_end' => $end, 'settings' => $settings,
        ]);
        $lock = null;
        try {
            $lock = Cache::lock('syndromic-surveillance-detection', 3600);
            if (! $lock->get()) {
                $run->update(['status' => 'Skipped', 'error' => 'Another detection is running.', 'finished_at' => now()]);

                return $run;
            }
            DB::transaction(function () use ($run, $settings, $start, $end): void {
                $categories = SyndromeCategory::where('is_active', true)->with(['rules' => fn ($query) => $query->whereNotNull('approved_at')->where('approved_at', '<=', now())->where('applies_from', '<=', $end)->latest('version')])->get();
                $earliestRule = $categories->flatMap->rules->min('applies_from') ?? $start->subWeeks((int) $settings['baseline_weeks']);
                if ($earliestRule > $start) {
                    $earliestRule = $start;
                }
                $records = collect();
                if ($earliestRule !== null) {
                    MedicalRecord::with('symptoms', 'queue', 'patient', 'vitalSign')
                        ->when(Schema::hasTable('surveillance_intakes'), fn ($query) => $query->whereNotExists(fn ($intake) => $intake->selectRaw('1')->from('surveillance_intakes')->whereColumn('surveillance_intakes.queue_id', 'medical_records.queue_id')->where('is_synthetic', true)))
                        ->whereDate('consultation_date', '>=', CarbonImmutable::parse($earliestRule)->setTimezone('Asia/Manila')->toDateString())
                        ->whereDate('consultation_date', '<=', $end->setTimezone('Asia/Manila')->toDateString())
                        ->orderBy('medical_record_id')->chunkById(500, function ($batch) use ($end, &$records): void {
                            foreach ($batch as $record) {
                                $time = $this->classifier->encounterTime($record);
                                if ($time === null || $time >= $end || ! $this->classifier->isValid($record)) {
                                    continue;
                                }
                                $this->classifier->classify($record);
                                if ($record->patient?->surveillance_residence === 'Alabang' && $record->patient->residence_verified_at !== null) {
                                    $records->put($record->getKey(), $record);
                                }
                            }
                        }, 'medical_record_id');
                }
                $currentAttendance = $this->inWindow($records, $start, $end)->count();
                $run->update(['eligible_cases' => $this->inWindow($records, $start, $end)->unique('patient_id')->count()]);
                $familySize = max(1, $categories->filter(fn ($category): bool => $category->rules->isNotEmpty())->count());
                foreach ($categories as $category) {
                    $rule = $category->rules->first();
                    $snapshots = $rule ? SurveillanceClassification::where('syndrome_category_id', $category->id)->where('syndrome_rule_version_id', $rule->id)
                        ->whereIn('medical_record_id', $records->keys())->orderBy('occurred_at')->orderBy('medical_record_id')->get() : collect();
                    $episodes = $this->episodes($snapshots->where('matches', true), $records, (int) $settings['episode_gap_hours']);
                    $currentCases = $episodes->filter(fn ($case): bool => $case->occurred_at >= $start && $case->occurred_at < $end);
                    $periods = [];
                    $rejected = 0;
                    for ($week = 1; $week <= (int) $settings['baseline_weeks']; $week++) {
                        $periodStart = $start->subWeeks($week);
                        $periodEnd = $end->subWeeks($week);
                        if ($rule === null || $periodStart < $rule->applies_from || $periodEnd > $start) {
                            $rejected++;

                            continue;
                        }
                        if ((int) $settings['baseline_weeks'] >= 60 && $this->seasonalDistance($end, $periodEnd) > 45) {
                            continue;
                        }
                        $attendance = $this->inWindow($records, $periodStart, $periodEnd)->count();
                        $covered = $snapshots->filter(fn ($case): bool => $case->occurred_at >= $periodStart && $case->occurred_at < $periodEnd)->count();
                        if ($attendance < $settings['minimum_attendance'] || $covered / max(1, $attendance) < $settings['minimum_coverage']) {
                            $rejected++;

                            continue;
                        }
                        $periods[] = [
                            'start' => $periodStart->toIso8601String(), 'end' => $periodEnd->toIso8601String(),
                            'count' => $episodes->filter(fn ($case): bool => $case->occurred_at >= $periodStart && $case->occurred_at < $periodEnd)->count(),
                            'attendance' => $attendance, 'coverage' => $covered / $attendance,
                            'attendance_record_ids' => $this->inWindow($records, $periodStart, $periodEnd)->keys()->all(),
                            'episode_classification_ids' => $episodes->filter(fn ($case): bool => $case->occurred_at >= $periodStart && $case->occurred_at < $periodEnd)->pluck('id')->all(),
                        ];
                    }
                    $currentCoverage = $snapshots->filter(fn ($case): bool => $case->occurred_at >= $start && $case->occurred_at < $end)->count() / max(1, $currentAttendance);
                    $calculation = [
                        'baseline_periods' => $periods, 'rejected_periods' => $rejected,
                        'current_attendance' => $currentAttendance, 'current_coverage' => $currentCoverage,
                        'current_attendance_record_ids' => $this->inWindow($records, $start, $end)->keys()->all(),
                        'scope_verification' => $records->map(fn ($record): array => ['patient_id' => $record->patient_id, 'residence' => $record->patient->surveillance_residence, 'verified_at' => $record->patient->residence_verified_at?->toIso8601String(), 'verified_by' => $record->patient->residence_verified_by])->all(),
                        'family_size' => $familySize, 'multiplicity' => 'Bonferroni per detection run',
                        'seasonality' => $settings['baseline_weeks'] >= 60 ? 'Matching weekdays within 45 calendar days of the current season' : 'Recent matching weekdays; annual seasonality not estimable',
                        'episode_gap_hours' => $settings['episode_gap_hours'], 'window' => '[start, end)',
                    ];
                    $resultData = [
                        'syndrome_category_id' => $category->id, 'syndrome_rule_version_id' => $rule?->id,
                        'syndrome_name' => $category->name, 'observed' => $currentCases->count(),
                        'outcome' => 'Insufficient Data', 'reason' => 'Insufficient reliable historical periods for this approved rule version.',
                    ];
                    if ($rule === null) {
                        $resultData['reason'] = 'A healthcare professional must approve a syndrome definition before detection.';
                    } elseif (! $settings['settings_reviewed']) {
                        $resultData['reason'] = 'An administrator must review the statistical configuration for development testing.';
                    } elseif (count($periods) >= (int) $settings['minimum_periods'] && $currentAttendance >= $settings['minimum_attendance'] && $currentCoverage >= $settings['minimum_coverage'] && array_sum(array_column($periods, 'count')) > 0) {
                        $stats = $this->statistics->evaluate($currentCases->count(), $currentAttendance, $periods, (float) $settings['overdispersion_ratio']);
                        $adjusted = min(1.0, $stats['probability'] * $familySize);
                        $outcome = $this->statistics->outcome($currentCases->count(), $stats['expected'], $adjusted, (float) $settings['alpha'], (float) $settings['watch_alpha']);
                        $resultData = array_merge($resultData, [
                            'expected' => $stats['expected'], 'probability' => $stats['probability'], 'adjusted_probability' => $adjusted,
                            'model' => $stats['model'], 'outcome' => $outcome,
                            'reason' => match ($outcome) {
                                'Alert' => 'Statistically significant increase requiring healthcare personnel review. This signal does not confirm an outbreak.',
                                'Watch' => 'An increase warrants monitoring but does not meet the configured alert threshold. This signal does not confirm an outbreak.',
                                default => 'No statistically unusual increase under the configured model.',
                            },
                        ]);
                        $calculation['dispersion'] = $stats['dispersion'];
                        $calculation['negative_binomial_size'] = $stats['size'];
                    } elseif ($currentAttendance < $settings['minimum_attendance'] || $currentCoverage < $settings['minimum_coverage']) {
                        $resultData['reason'] = 'Current attendance or structured-symptom coverage is below the configured reliability requirement.';
                    } elseif (array_sum(array_column($periods, 'count')) === 0) {
                        $resultData['reason'] = 'The historical baseline has no syndrome episodes; an expected rate cannot be reliably estimated.';
                    }
                    $result = $run->results()->create($resultData + ['calculation' => $calculation]);
                    $result->cases()->attach($currentCases->pluck('id')->all());
                    if ($result->outcome === 'Alert') {
                        $this->alert($result, $currentCases);
                    }
                }
                $run->update(['status' => 'Completed', 'finished_at' => now()]);
            });
        } catch (Throwable $exception) {
            $run->update(['status' => 'Failed', 'error' => 'Detection failed; no partial results were retained. See the application log.', 'finished_at' => now()]);
            report($exception);
            throw $exception;
        } finally {
            $lock?->release();
        }

        return $run->refresh();
    }

    /** @param Collection<int, MedicalRecord> $records
     * @return Collection<int, MedicalRecord>
     */
    private function inWindow(Collection $records, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $records->filter(function (MedicalRecord $record) use ($start, $end): bool {
            $time = $this->classifier->encounterTime($record);

            return $time >= $start && $time < $end;
        });
    }

    /** @param Collection<int, SurveillanceClassification> $cases
     * @param  Collection<int, MedicalRecord>  $records
     * @return Collection<int, SurveillanceClassification>
     */
    private function episodes(Collection $cases, Collection $records, int $gapHours): Collection
    {
        $lastSeen = [];

        return $cases->filter(function (SurveillanceClassification $case) use (&$lastSeen, $records, $gapHours): bool {
            $patientId = $records->get($case->medical_record_id)->patient_id;
            $previous = $lastSeen[$patientId] ?? null;
            $lastSeen[$patientId] = $case->occurred_at;

            return $previous === null || $case->occurred_at >= $previous->addHours($gapHours);
        });
    }

    private function seasonalDistance(CarbonImmutable $current, CarbonImmutable $historical): int
    {
        $distance = abs($current->dayOfYear - $historical->dayOfYear);

        return min($distance, 366 - $distance);
    }

    /** @param Collection<int, SurveillanceClassification> $cases */
    private function alert(SurveillanceResult $result, Collection $cases): void
    {
        $keys = $cases->pluck('id')->all();
        sort($keys);
        $fingerprint = hash('sha256', $result->syndrome_rule_version_id.':'.implode(',', $keys));
        if (SurveillanceAlert::where('fingerprint', $fingerprint)->exists()) {
            return;
        }
        $previousAlerts = SurveillanceAlert::where('syndrome_category_id', $result->syndrome_category_id)
            ->whereHas('result', fn ($query) => $query->where('syndrome_rule_version_id', $result->syndrome_rule_version_id))
            ->with('result.cases')->get();
        foreach ($previousAlerts as $previousAlert) {
            $previousKeys = $previousAlert->result->cases->pluck('id')->all();
            if (array_diff($keys, $previousKeys) === [] || (in_array($previousAlert->status, ['New', 'Under Review', 'Escalated'], true) && array_intersect($keys, $previousKeys) !== [])) {
                return;
            }
        }
        SurveillanceAlert::create([
            'reference' => 'SUR-'.now('Asia/Manila')->format('Ymd').'-'.Str::upper((string) Str::ulid()),
            'syndrome_category_id' => $result->syndrome_category_id, 'surveillance_result_id' => $result->id,
            'fingerprint' => $fingerprint, 'detected_at' => now('UTC'), 'status' => 'New',
            'syndrome' => $result->syndrome_name, 'mode' => 'development', 'snapshot' => ['result_id' => $result->id, 'engine' => 'structured-statistical-v1'],
            'peak_count' => $result->observed, 'first_detected_at' => now('UTC'), 'last_qualifying_at' => now('UTC'),
        ]);
    }
}
