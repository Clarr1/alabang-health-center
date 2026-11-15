<?php

namespace Database\Factories;

use App\Models\PatientInformation;
use App\Models\PatientProfileAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PatientProfileAudit> */
class PatientProfileAuditFactory extends Factory
{
    public function definition(): array
    {
        return ['patient_id' => PatientInformation::factory(), 'user_id' => User::factory(), 'action' => 'profile-updated', 'details' => ['before' => [], 'after' => []]];
    }
}
