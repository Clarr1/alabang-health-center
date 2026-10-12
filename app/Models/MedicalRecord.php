<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'prescription',
        'treatment',
        'consultation_notes',
        'consultation_date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'consultation_date' => 'date',
            'created_at' => 'datetime',
        ];
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
