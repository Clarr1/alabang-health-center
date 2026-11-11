<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SyndromeRuleVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['approved_at' => 'immutable_datetime', 'applies_from' => 'immutable_datetime'];
    }

    public function symptoms(): BelongsToMany
    {
        return $this->belongsToMany(Symptom::class, 'syndrome_rule_symptoms')->withPivot('symptom_name');
    }
}
