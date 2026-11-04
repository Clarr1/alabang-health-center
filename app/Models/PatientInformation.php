<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientInformation extends Model
{
    use HasFactory;

    protected $table = 'patient_information';

    protected $primaryKey = 'patient_id';

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'age',
        'sex',
        'address',
        'contact_number',
        'sms_consent',
        'emergency_contact',
        'registration_date',
        'status',
    ];

    protected function casts(): array
    {
        return ['sms_consent' => 'boolean'];
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'patient_id', 'patient_id');
    }

    public function queues(): HasMany
    {
        return $this->hasMany(Queue::class, 'patient_id', 'patient_id');
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class, 'patient_id', 'patient_id');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'patient_id', 'patient_id');
    }
}
