<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    protected $table = 'vital_signs';

    protected $primaryKey = 'vital_id';

    public function patient(): BelongsTo
    {
        return $this->belongsTo(
            PatientInformation::class,
            'patient_id',
            'patient_id'
        );
    }
}