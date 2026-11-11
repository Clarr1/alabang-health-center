<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveillanceRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'window_start' => 'immutable_datetime', 'window_end' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    public function results(): HasMany
    {
        return $this->hasMany(SurveillanceResult::class);
    }
}
