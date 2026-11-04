<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FollowUp extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'immutable_datetime', 'version' => 'integer', 'notification_version' => 'integer'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientInformation::class, 'patient_id', 'patient_id');
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id', 'medical_record_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FollowUpMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FollowUpEvent::class);
    }
}
