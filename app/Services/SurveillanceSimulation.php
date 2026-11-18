<?php

namespace App\Services;

use App\Models\SurveillanceSetting;
use App\Models\SyndromeCategory;
use Illuminate\Support\Facades\Gate;

class SurveillanceSimulation
{
    public function __construct(private SurveillanceStatistics $statistics) {}

    /**
     * Exercise the statistical stage with fictional aggregate counts in memory.
     * No classification, patient, run, or alert records are created or modified.
     *
     * @return array{completed_at: string, category: string, minimum_cases: int, is_active: bool, cases: int, historical_cases: int, test_alerts: int, test_signals: int, results: array<int, array<string, mixed>>}
     */
    public function run(int $categoryId): array
    {
        Gate::authorize('surveillance-manage');
        $settings = SurveillanceSetting::current();
        $category = SyndromeCategory::findOrFail($categoryId);
        $minimumCases = SurveillanceSetting::minimumCases($categoryId, $settings);
        $familySize = max(1, SyndromeCategory::where('is_active', true)->whereHas('rules', fn ($query) => $query->whereNotNull('approved_at')->where('approved_at', '<=', now())->where('applies_from', '<=', now()))->count());
        $periods = array_fill(0, (int) $settings['baseline_weeks'], ['count' => 1, 'attendance' => 10]);
        $scenarios = [
            ['name' => 'SIMULATED Below minimum', 'observed' => max(0, $minimumCases - 1), 'periods' => $periods],
            ['name' => 'SIMULATED At minimum', 'observed' => $minimumCases, 'periods' => $periods],
            ['name' => 'SIMULATED Above minimum', 'observed' => $minimumCases + 1, 'periods' => $periods],
            ['name' => 'SIMULATED Stable pattern', 'observed' => $minimumCases, 'periods' => array_fill(0, (int) $settings['baseline_weeks'], ['count' => $minimumCases, 'attendance' => max(20, $minimumCases + 1)])],
            ['name' => 'SIMULATED Missing history', 'observed' => 3, 'periods' => []],
        ];
        $results = [];
        foreach ($scenarios as $scenario) {
            $attendance = max(20, $minimumCases + 1);
            $result = ['name' => $scenario['name'], 'observed' => $scenario['observed'], 'expected' => null, 'threshold_reached' => $scenario['observed'] >= $minimumCases, 'outcome' => 'Insufficient Data'];
            if (count($scenario['periods']) >= (int) $settings['minimum_periods'] && (int) $settings['minimum_attendance'] <= $attendance && $settings['settings_reviewed']) {
                $stats = $this->statistics->evaluate($scenario['observed'], $attendance, $scenario['periods'], (float) $settings['overdispersion_ratio']);
                $result['expected'] = $stats['expected'];
                $result['outcome'] = $this->statistics->outcome($scenario['observed'], $stats['expected'], min(1.0, $stats['probability'] * $familySize), (float) $settings['alpha'], (float) $settings['watch_alpha'], $minimumCases);
            }
            $results[] = $result;
        }

        return [
            'completed_at' => now('Asia/Manila')->format('d M Y H:i'),
            'category' => $category->name, 'minimum_cases' => $minimumCases, 'is_active' => $category->is_active,
            'cases' => array_sum(array_column($scenarios, 'observed')),
            'historical_cases' => array_sum(array_map(fn (array $scenario): int => array_sum(array_column($scenario['periods'], 'count')), $scenarios)),
            'test_alerts' => count(array_filter($results, fn (array $result): bool => $result['outcome'] === 'Alert')),
            'test_signals' => count(array_filter($results, fn (array $result): bool => $result['outcome'] === 'Signal')),
            'results' => $results,
        ];
    }
}
