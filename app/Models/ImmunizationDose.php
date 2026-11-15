<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImmunizationDose extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientInformation::class, 'patient_id', 'patient_id');
    }

    protected function casts(): array
    {
        return ['administered_at' => 'immutable_date'];
    }
}
