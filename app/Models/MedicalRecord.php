<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MedicalRecord extends Model
{
    protected $table = 'medical_records';

    protected $primaryKey = 'medical_record_id';

    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'queue_id',
        'vital_signs_id',
        'handled_by',
        'diagnosis',
        'diagnosis_id',
        'chief_complaint',
        'custom_symptoms',
        'laboratory_requests',
        'prescription',
        'treatment',
        'consultation_notes',
        'consultation_date',
        'follow_up_date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_symptoms' => 'array',
            'consultation_date' => 'date',
            'follow_up_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function followUp(): HasOne
    {
        return $this->hasOne(FollowUp::class, 'medical_record_id', 'medical_record_id');
    }

    public function primaryDiagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class, 'diagnosis_id');
    }

    public function symptoms(): BelongsToMany
    {
        return $this->belongsToMany(Symptom::class, 'medical_record_symptoms', 'medical_record_id', 'symptom_id')->withPivot('symptom_name');
    }

    /** @return array<int, string> */
    public function symptomNames(): array
    {
        return $this->symptoms->toBase()->map(fn (Symptom $symptom): string => $symptom->pivot->symptom_name)->merge($this->custom_symptoms ?? [])->all();
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class, 'queue_id', 'queue_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientInformation::class, 'patient_id', 'patient_id');
    }

    public function vitalSign(): BelongsTo
    {
        return $this->belongsTo(VitalSign::class, 'vital_signs_id', 'vital_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by', 'user_id');
    }
}
