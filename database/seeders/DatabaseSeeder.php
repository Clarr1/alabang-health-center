<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Development seed data is permitted only in local or testing environments.');
        }

        $previousSms = config('textbee.enabled');
        $previousMailer = config('mail.default');
        config(['textbee.enabled' => false, 'mail.default' => 'array']);

        try {
            DB::transaction(function (): void {
                $this->call([
                    UserSeeder::class,
                    SymptomSeeder::class,
                    DiagnosisSeeder::class,
                    PatientInformationSeeder::class,
                    VitalSignsSeeder::class,
                    MedicalRecordSeeder::class,
                    SyndromicEarlyWarningSeeder::class,
                ]);
            });
        } finally {
            config(['textbee.enabled' => $previousSms, 'mail.default' => $previousMailer]);
        }
    }
}
