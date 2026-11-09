<?php

namespace App\Models;

use Database\Factories\SymptomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Symptom extends Model
{
    /** @use HasFactory<SymptomFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function medicalRecords(): BelongsToMany
    {
        return $this->belongsToMany(MedicalRecord::class, 'medical_record_symptoms', 'symptom_id', 'medical_record_id')->withPivot('symptom_name');
    }
}
