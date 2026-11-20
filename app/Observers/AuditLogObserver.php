<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditLogObserver
{
    /** @var \WeakMap<Model, array<string, mixed>>|null */
    private static ?\WeakMap $originals = null;

    public function saving(Model $model): void
    {
        if (! $model->exists) {
            return;
        }

        self::$originals ??= new \WeakMap;
        self::$originals[$model] = collect($model->getDirty())
            ->mapWithKeys(fn (mixed $value, string $field): array => [$field => $model->getOriginal($field)])
            ->all();
    }

    public function updated(Model $model): void
    {
        $original = self::$originals?->offsetExists($model) ? self::$originals[$model] : [];
        self::$originals?->offsetUnset($model);
        app(AuditLogger::class)->recordUpdate($model, $original, $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        app(AuditLogger::class)->recordDelete($model);
    }
}
