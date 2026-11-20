<?php

namespace Database\Seeders;

use App\Models\PatientInformation;
use App\Models\User;
use App\Models\VitalSign;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class VitalSignsSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::where('email', 'staff@example.test')->sole();
        foreach (PatientInformation::where('address', 'like', PatientInformationSeeder::ADDRESS_PREFIX.'%')->orderBy('patient_id')->get() as $index => $patient) {
            $height = [170, 158, 168, 160, 175, 155, 145, 125, 172, 162][$index % 10];
            $weight = [65, 55, 72, 58, 82, 54, 38, 25, 70, 56][$index % 10];
            $time = CarbonImmutable::parse($patient->registration_date, 'Asia/Manila')->setTime(9, $index)->utc();
            VitalSign::firstOrCreate(['patient_id' => $patient->getKey(), 'recorded_at' => $time], [
                'recorded_by' => $staff->getKey(), 'blood_pressure' => $index % 2 === 0 ? '118/78' : '110/70',
                'temperature' => 36.6, 'pulse_rate' => 74 + $index, 'respiratory_rate' => 18,
                'height' => $height, 'weight' => $weight, 'bmi' => round($weight / (($height / 100) ** 2), 2),
                'oxygen_saturation' => 98,
            ]);
        }
    }
}
