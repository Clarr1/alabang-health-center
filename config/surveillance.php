<?php

return [
    'demo' => (bool) env('SURVEILLANCE_DEMO', false),
    /**
     * Upper-tail probabilities after the existing per-run Bonferroni correction.
     * These development presets do not control false positives across future runs.
     */
    'sensitivities' => [
        'low' => ['label' => 'Low Sensitivity', 'alpha' => 0.001, 'watch_alpha' => 0.01],
        'balanced' => ['label' => 'Balanced (Recommended)', 'alpha' => 0.01, 'watch_alpha' => 0.05],
        'high' => ['label' => 'High Sensitivity', 'alpha' => 0.05, 'watch_alpha' => 0.1],
    ],
    'defaults' => [
        'settings_reviewed' => false,
        'window_hours' => 48,
        'baseline_weeks' => 12,
        'minimum_periods' => 8,
        'minimum_attendance' => 5,
        'minimum_coverage' => 0.8,
        'episode_gap_hours' => 72,
        'alpha' => 0.01,
        'watch_alpha' => 0.05,
        'overdispersion_ratio' => 1.5,
        'frequency_minutes' => 60,
    ],
];
