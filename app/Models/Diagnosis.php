<?php

namespace App\Models;

use Database\Factories\DiagnosisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diagnosis extends Model
{
    /** @use HasFactory<DiagnosisFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'description', 'is_active', 'review_status'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    protected static function booted(): void
    {
        static::saving(function (Diagnosis $diagnosis): void {
            $diagnosis->normalized_name = self::normalizeName($diagnosis->name);
        });
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }
}
