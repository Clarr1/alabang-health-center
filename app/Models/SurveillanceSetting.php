<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveillanceSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    /** @return array<string, mixed> */
    public static function current(): array
    {
        return array_replace(config('surveillance.defaults'), self::find(1)?->values ?? []);
    }
}
