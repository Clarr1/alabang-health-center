<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Queue extends Model
{
    use HasFactory;

    public const STATUSES = ['Waiting', 'Called', 'Vital Signs', 'Ready for Consultation', 'In Consultation', 'Completed', 'Cancelled', 'No Show'];

    public const TERMINAL_STATUSES = ['Completed', 'Cancelled', 'No Show'];

    protected $primaryKey = 'queue_id';

    protected $fillable = ['patient_id', 'queue_number', 'queue_date', 'status', 'called_at', 'completed_at', 'assigned_by'];

    protected function casts(): array
    {
        return ['queue_date' => 'date', 'called_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function setQueueDateAttribute(mixed $value): void
    {
        $this->attributes['queue_date'] = Carbon::parse($value)->toDateString();
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'user_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientInformation::class, 'patient_id', 'patient_id');
    }

    public function vitalSign(): HasOne
    {
        return $this->hasOne(VitalSign::class, 'queue_id', 'queue_id');
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class, 'queue_id', 'queue_id');
    }

    /** @return array<string, string> */
    public function actions(): array
    {
        return match ($this->status) {
            'Waiting' => ['Called' => 'Call Patient', 'Cancelled' => 'Cancel'],
            'Called' => ['Vital Signs' => 'Start Vital Signs', 'No Show' => 'Mark No Show', 'Cancelled' => 'Cancel'],
            'Vital Signs' => ['Ready for Consultation' => 'Ready for Consultation', 'Cancelled' => 'Cancel'],
            'Ready for Consultation' => ['In Consultation' => 'Start Consultation', 'Cancelled' => 'Cancel'],
            'In Consultation' => ['Completed' => 'Complete', 'Cancelled' => 'Cancel'],
            default => [],
        };
    }

    public function badgeClasses(): string
    {
        return match ($this->status) {
            'Waiting' => 'bg-yellow-100 text-yellow-800',
            'Called' => 'bg-blue-100 text-blue-800',
            'Vital Signs' => 'bg-purple-100 text-purple-800',
            'Ready for Consultation' => 'bg-indigo-100 text-indigo-800',
            'In Consultation' => 'bg-cyan-100 text-cyan-800',
            'Completed' => 'bg-green-100 text-green-800',
            'Cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
