<?php

namespace Database\Factories;

use App\Models\PatientInformation;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VitalSign> */
class VitalSignFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_id' => PatientInformation::factory(), 'recorded_by' => User::factory(), 'blood_pressure' => '120/80', 'temperature' => 36.5, 'height' => 160, 'weight' => 55, 'bmi' => 21.5, 'recorded_at' => now()];
    }
}
