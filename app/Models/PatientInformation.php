<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientInformation extends Model
{
    protected $table = 'patient_information';

    protected $primaryKey = 'patient_id';

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class, 'patient_id', 'patient_id');
    }
}