<?php

namespace Database\Factories;

use App\Models\ImmunizationDose;
use App\Models\PatientInformation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImmunizationDose> */
class ImmunizationDoseFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_id' => PatientInformation::factory(), 'vaccine_name' => 'IPV', 'schedule' => 'Other', 'dose' => '1', 'status' => 'Pending', 'recorded_by' => User::factory()];
    }
}
