<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VitalSign extends Model
{
    protected $table = 'vital_signs';

    protected $primaryKey = 'vital_id';

    protected $fillable = [
        'patient_id',
        'recorded_by',
        'blood_pressure',
        'temperature',
        'pulse_rate',
        'respiratory_rate',
        'height',
        'weight',
        'bmi',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(
            PatientInformation::class,
            'patient_id',
            'patient_id'
        );
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'vital_signs_id', 'vital_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'user_id');
    }
}
