<?php

namespace Database\Factories;

use App\Models\PatientInformation;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientProfile> */
class PatientProfileFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_id' => PatientInformation::factory(), 'prepared_by' => User::factory(), 'completed_at' => now(), 'civil_status' => 'Single'];
    }
}
