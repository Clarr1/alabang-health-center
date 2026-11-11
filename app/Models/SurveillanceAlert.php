<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveillanceAlert extends Model
{
    public const STATUSES = ['New', 'Under Review', 'Reviewed', 'Escalated', 'Dismissed', 'Resolved'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['detected_at' => 'immutable_datetime', 'first_detected_at' => 'immutable_datetime', 'snapshot' => 'array'];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(SurveillanceResult::class, 'surveillance_result_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SurveillanceAlertReview::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'user_id');
    }
}
