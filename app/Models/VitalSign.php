<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function patient(): BelongsTo
    {
        return $this->belongsTo(
            PatientInformation::class,
            'patient_id',
            'patient_id'
        );
    }
}
