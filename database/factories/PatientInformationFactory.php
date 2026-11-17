<?php

namespace Database\Factories;

use App\Models\PatientInformation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientInformation> */
class PatientInformationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(),
            'birthdate' => '1990-01-01', 'age' => 36, 'sex' => 'Female',
            'address' => fake()->address(), 'contact_number' => '09171234567',
            'emergency_contact' => 'Jose Santos, relative, 09171234568',
            'registration_date' => now('Asia/Manila')->toDateString(), 'status' => 'Active',
        ];
    }
}
