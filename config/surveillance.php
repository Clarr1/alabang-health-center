<?php

return [
    'demo' => (bool) env('SURVEILLANCE_DEMO', false),
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
