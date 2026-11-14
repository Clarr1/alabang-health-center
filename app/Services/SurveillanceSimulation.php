<?php

namespace App\Services;

use App\Models\SurveillanceSetting;
use Illuminate\Support\Facades\Gate;

class SurveillanceSimulation
{
    public function __construct(private SurveillanceStatistics $statistics) {}

    /**
     * Exercise the statistical stage with fictional aggregate counts in memory.
     * No classification, patient, run, or alert records are created or modified.
     *
     * @return array{completed_at: string, sensitivity: string, cases: int, historical_cases: int, test_alerts: int, results: array<int, array<string, mixed>>}
     */
    public function run(): array
    {
        Gate::authorize('surveillance-manage');
        $settings = SurveillanceSetting::current();
        $periods = array_fill(0, (int) $settings['baseline_weeks'], ['count' => 1, 'attendance' => 10]);
        $scenarios = [
            ['name' => 'SIMULATED Stable pattern', 'observed' => 2, 'periods' => $periods],
            ['name' => 'SIMULATED Unusual increase', 'observed' => 12, 'periods' => $periods],
            ['name' => 'SIMULATED Missing history', 'observed' => 3, 'periods' => []],
        ];
        $results = [];
        foreach ($scenarios as $scenario) {
            $result = ['name' => $scenario['name'], 'observed' => $scenario['observed'], 'expected' => null, 'outcome' => 'Insufficient Data'];
            if (count($scenario['periods']) >= (int) $settings['minimum_periods'] && (int) $settings['minimum_attendance'] <= 20 && $settings['settings_reviewed']) {
                $stats = $this->statistics->evaluate($scenario['observed'], 20, $scenario['periods'], (float) $settings['overdispersion_ratio']);
                $result['expected'] = $stats['expected'];
                $result['outcome'] = $this->statistics->outcome($scenario['observed'], $stats['expected'], min(1.0, $stats['probability'] * count($scenarios)), (float) $settings['alpha'], (float) $settings['watch_alpha']);
            }
            $results[] = $result;
        }

        return [
            'completed_at' => now('Asia/Manila')->format('d M Y H:i'),
            'sensitivity' => SurveillanceSetting::sensitivity(),
            'cases' => array_sum(array_column($scenarios, 'observed')),
            'historical_cases' => count($periods) * 2,
            'test_alerts' => count(array_filter($results, fn (array $result): bool => $result['outcome'] === 'Alert')),
            'results' => $results,
        ];
    }
}
