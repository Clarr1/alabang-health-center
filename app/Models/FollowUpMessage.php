<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_at' => 'immutable_datetime', 'attempted_at' => 'immutable_datetime', 'checked_at' => 'immutable_datetime', 'attempt_history' => 'array', 'version' => 'integer'];
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }
}
