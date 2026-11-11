<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SurveillanceResult extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['calculation' => 'array', 'expected' => 'float', 'probability' => 'float', 'adjusted_probability' => 'float'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SurveillanceRun::class, 'surveillance_run_id');
    }

    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(SurveillanceClassification::class, 'surveillance_result_cases');
    }
}
