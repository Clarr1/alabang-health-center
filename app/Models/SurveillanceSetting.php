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
        return array_replace(config('surveillance.defaults'), self::find(1)?->values ?? [], ['window_hours' => 48]);
    }

    public static function sensitivity(): string
    {
        $settings = self::current();
        foreach (config('surveillance.sensitivities') as $key => $profile) {
            if ((float) $settings['alpha'] === (float) $profile['alpha'] && (float) $settings['watch_alpha'] === (float) $profile['watch_alpha']) {
                return $key;
            }
        }

        return 'custom';
    }
}
