<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Audit history is immutable.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Audit history is immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'changed_fields' => 'encrypted:array',
            'previous_values' => 'encrypted:array',
            'new_values' => 'encrypted:array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
