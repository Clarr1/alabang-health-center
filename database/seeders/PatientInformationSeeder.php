<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PatientInformationSeeder extends Seeder
{
    public function run(): void
    {
        $patients = [
            [
                'qr_code' => 'PAT-000001',
                'first_name' => 'Juan',
                'middle_name' => 'Santos',
                'last_name' => 'Dela Cruz',
                'birthdate' => '1998-05-15',
                'age' => 28,
                'sex' => 'Male',
                'address' => 'Alabang, Muntinlupa City',
                'contact_number' => '09171234567',
                'emergency_contact' => '09181234567',
                'registration_date' => '2026-09-10',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'qr_code' => 'PAT-000002',
                'first_name' => 'Maria',
                'middle_name' => 'Garcia',
                'last_name' => 'Santos',
                'birthdate' => '2001-08-20',
                'age' => 25,
                'sex' => 'Female',
                'address' => 'Bayanan, Muntinlupa City',
                'contact_number' => '09192345678',
                'emergency_contact' => '09201234567',
                'registration_date' => '2026-09-10',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'qr_code' => 'PAT-000003',
                'first_name' => 'Pedro',
                'middle_name' => null,
                'last_name' => 'Reyes',
                'birthdate' => '1985-03-10',
                'age' => 41,
                'sex' => 'Male',
                'address' => 'Putatan, Muntinlupa City',
                'contact_number' => '09213456789',
                'emergency_contact' => '09224567890',
                'registration_date' => '2026-09-10',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'qr_code' => 'PAT-000004',
                'first_name' => 'Ana',
                'middle_name' => 'Lopez',
                'last_name' => 'Garcia',
                'birthdate' => '1995-11-25',
                'age' => 30,
                'sex' => 'Female',
                'address' => 'Tunasan, Muntinlupa City',
                'contact_number' => '09235678901',
                'emergency_contact' => '09246789012',
                'registration_date' => '2026-09-10',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'qr_code' => 'PAT-000005',
                'first_name' => 'Carlos',
                'middle_name' => 'Mendoza',
                'last_name' => 'Cruz',
                'birthdate' => '1978-01-30',
                'age' => 48,
                'sex' => 'Male',
                'address' => 'Sucat, Muntinlupa City',
                'contact_number' => '09257890123',
                'emergency_contact' => '09268901234',
                'registration_date' => '2026-09-10',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('patient_information')->insert($patients);
    }
}