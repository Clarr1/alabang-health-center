<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MedicalRecord> */
class MedicalRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vital_signs_id' => VitalSignFactory::new(),
            'patient_id' => fn (array $attributes): int => VitalSign::findOrFail($attributes['vital_signs_id'])->patient_id,
            'handled_by' => User::factory(['role' => 'Doctor']), 'diagnosis' => 'Private diagnosis',
            'consultation_date' => now('Asia/Manila')->toDateString(), 'follow_up_date' => now('Asia/Manila')->addDays(3)->toDateString(), 'created_at' => now(),
        ];
    }
}
