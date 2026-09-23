<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VitalSignsSeeder extends Seeder
{
    public function run(): void
    {
        $vitalSigns = [
            [
                'patient_id' => 1,
                'recorded_by' => 1,
                'blood_pressure' => '120/80',
                'temperature' => 36.5,
                'pulse_rate' => 72,
                'respiratory_rate' => 16,
                'height' => 170.00,
                'weight' => 65.00,
                'bmi' => 22.5,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'patient_id' => 2,
                'recorded_by' => 1,
                'blood_pressure' => '110/70',
                'temperature' => 36.7,
                'pulse_rate' => 75,
                'respiratory_rate' => 18,
                'height' => 158.00,
                'weight' => 55.00,
                'bmi' => 22.0,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'patient_id' => 3,
                'recorded_by' => 1,
                'blood_pressure' => '130/85',
                'temperature' => 37.1,
                'pulse_rate' => 80,
                'respiratory_rate' => 20,
                'height' => 168.00,
                'weight' => 78.00,
                'bmi' => 27.6,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'patient_id' => 4,
                'recorded_by' => 1,
                'blood_pressure' => '115/75',
                'temperature' => 36.6,
                'pulse_rate' => 70,
                'respiratory_rate' => 17,
                'height' => 160.00,
                'weight' => 58.00,
                'bmi' => 22.7,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'patient_id' => 5,
                'recorded_by' => 1,
                'blood_pressure' => '125/80',
                'temperature' => 36.9,
                'pulse_rate' => 78,
                'respiratory_rate' => 18,
                'height' => 175.00,
                'weight' => 82.00,
                'bmi' => 26.8,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('vital_signs')->insert($vitalSigns);
    }
}