<?php

namespace Database\Seeders;

use App\Models\PatientInformation;
use App\Models\User;
use App\Services\PatientCategories;
use Illuminate\Database\Seeder;

class PatientInformationSeeder extends Seeder
{
    public const ADDRESS_PREFIX = 'FICTIONAL DEMO HOME ';

    public function run(): void
    {
        $names = [
            ['Juan', 'Santos', 'Dela Cruz', '1998-05-15', 'Male'],
            ['Maria', 'Garcia', 'Santos', '2001-08-20', 'Female'],
            ['Pedro', 'Ramos', 'Reyes', '1985-03-10', 'Male'],
            ['Ana', 'Lopez', 'Garcia', '1995-11-25', 'Female'],
            ['Carlos', 'Mendoza', 'Cruz', '1978-01-30', 'Male'],
            ['Elena', 'Reyes', 'Bautista', '1960-06-12', 'Female'],
            ['Miguel', 'Torres', 'Navarro', '2014-02-18', 'Male'],
            ['Sofia', 'Castillo', 'Ramos', '2018-09-04', 'Female'],
            ['Andres', 'Flores', 'Villanueva', '1990-12-08', 'Male'],
            ['Isabel', 'Aquino', 'Domingo', '2005-04-22', 'Female'],
        ];
        $staff = User::where('email', 'staff@example.test')->sole();

        foreach ($names as $index => [$firstName, $middleName, $lastName, $birthdate, $sex]) {
            $patient = PatientInformation::firstOrCreate([
                'address' => self::ADDRESS_PREFIX.($index + 1).', Barangay Alabang, Muntinlupa City',
                'first_name' => $firstName, 'last_name' => $lastName, 'birthdate' => $birthdate,
            ], [
                'middle_name' => $middleName, 'sex' => $sex, 'surveillance_residence' => 'Alabang',
                'contact_number' => null, 'emergency_contact' => null, 'sms_consent' => false,
                'registration_date' => now('Asia/Manila')->subDays(180)->toDateString(), 'status' => 'Active',
                'patient_category' => app(PatientCategories::class)->suggest($birthdate),
            ]);
            $patient->registrationProfile()->firstOrCreate([], [
                'prepared_by' => $staff->getKey(),
                'completed_at' => $patient->registration_date,
                'pertinent_information' => 'FICTIONAL development record. Never use for patient care.',
            ]);
        }
    }
}
