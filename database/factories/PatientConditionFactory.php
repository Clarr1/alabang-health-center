<?php

namespace Database\Factories;

use App\Models\PatientCondition;
use App\Models\PatientInformation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientCondition> */
class PatientConditionFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_id' => PatientInformation::factory(), 'name' => 'Asthma', 'status' => 'Reported', 'recorded_by' => User::factory()];
    }
}
