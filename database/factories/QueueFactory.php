<?php

namespace Database\Factories;

use App\Models\PatientInformation;
use App\Models\Queue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/** @extends Factory<Queue> */
class QueueFactory extends Factory
{
    public function definition(): array
    {
        $date = now('Asia/Manila')->toDateString();
        DB::table('queue_days')->insertOrIgnore(['queue_date' => $date, 'last_number' => 0]);

        return [
            'patient_id' => PatientInformation::factory(), 'queue_date' => $date,
            'queue_number' => fake()->unique()->numberBetween(1, 1000000),
            'status' => 'Waiting',
        ];
    }
}
