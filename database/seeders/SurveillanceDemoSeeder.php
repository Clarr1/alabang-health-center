<?php

namespace Database\Seeders;

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\SurveillanceAlert;
use App\Models\SurveillanceRun;
use App\Models\SurveillanceSetting;
use App\Models\Symptom;
use App\Models\SyndromeCategory;
use App\Models\User;
use App\Services\SurveillanceAdministration;
use App\Services\SurveillanceEngine;
use Carbon\CarbonImmutable;
use Database\Factories\MedicalRecordFactory;
use Database\Factories\PatientInformationFactory;
use Database\Factories\VitalSignFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SurveillanceDemoSeeder extends Seeder
{
    public const PASSWORD = 'SurveillanceDemo!2026';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Fictional demonstrations are not permitted in production.');
        }
        $database = (string) DB::connection()->getDatabaseName();
        if (! str_starts_with($database, 'ahc_surveillance_demo_') && ! (app()->environment('testing') && $database === ':memory:')) {
            throw new RuntimeException('Fictional data is permitted only in a separate ahc_surveillance_demo_ database.');
        }
        if (User::exists() || PatientInformation::exists() || MedicalRecord::exists() || SyndromeCategory::exists()) {
            throw new RuntimeException('The demo requires an empty database; existing records will not be changed.');
        }
        $previousUser = Auth::user();
        $previousSms = config('textbee.enabled');
        $previousMailer = config('mail.default');
        config(['textbee.enabled' => false, 'mail.default' => 'array']);
        try {
            DB::transaction(function (): void {
                $accounts = [];
                foreach (['admin' => 'Admin', 'doctor' => 'Doctor', 'staff' => 'Local Health Unit Staff'] as $key => $role) {
                    $accounts[$key] = User::factory()->create([
                        'first_name' => 'SIMULATED', 'last_name' => ucfirst($key), 'middle_name' => null,
                        'name' => 'SIMULATED '.ucfirst($key), 'username' => 'surveillance_demo_'.$key,
                        'email' => $key.'@surveillance-demo.test', 'password' => Hash::make(self::PASSWORD), 'role' => $role,
                    ]);
                }
                Auth::setUser($accounts['admin']);
                $end = CarbonImmutable::now('UTC')->startOfMinute();
                $administration = app(SurveillanceAdministration::class);
                foreach (['Febrile' => 'Fever', 'Respiratory' => 'Cough', 'Gastrointestinal' => 'Diarrhea'] as $name => $symptom) {
                    $rule = $administration->saveDefinition([
                        'name' => 'SIMULATED '.$name, 'description' => 'FICTIONAL software demonstration. This symptom mapping is not a validated clinical definition.',
                        'is_active' => true, 'symptom_ids' => [Symptom::where('name', $symptom)->value('id')], 'minimum_matches' => 1,
                        'applies_from' => $end->subWeeks(16)->setTimezone('Asia/Manila')->toDateString(),
                        'rationale' => 'SIMULATED approval by a fictional demo account solely to exercise the software. No clinical or operational approval is implied.',
                    ]);
                    $administration->approve($rule->id);
                }
                SyndromeCategory::create(['name' => 'SIMULATED Rash - Unreviewed', 'description' => 'FICTIONAL unapproved category demonstrating Insufficient Data.', 'is_active' => true]);
                $settings = SurveillanceSetting::current();
                $settings['settings_reviewed'] = true;
                $administration->saveSettings($settings);
                for ($week = 12; $week >= 1; $week--) {
                    for ($index = 0; $index < 20; $index++) {
                        $symptom = match ($index) {
                            0 => 'Fever', 1 => 'Cough', 2 => 'Diarrhea', default => 'Headache'
                        };
                        $this->encounter($end->subWeeks($week)->subHours(2)->subMinutes($index), $symptom, $accounts['doctor'], $accounts['staff']);
                    }
                }
                $firstCurrentPatient = null;
                for ($index = 0; $index < 20; $index++) {
                    $symptom = match (true) {
                        $index < 12 => 'Fever', $index < 17 => 'Cough', $index === 17 => 'Diarrhea', default => 'Headache'
                    };
                    $patient = $this->encounter($end->subHours(2)->subMinutes($index), $symptom, $accounts['doctor'], $accounts['staff']);
                    $firstCurrentPatient ??= $patient;
                }
                $this->encounter($end->subHour(), 'Fever', $accounts['doctor'], $accounts['staff'], patient: $firstCurrentPatient);
                $this->encounter($end->subHour(), 'Fever', $accounts['doctor'], $accounts['staff'], residence: 'Outside Scope');
                $this->encounter($end->subHour(), 'Fever', $accounts['doctor'], $accounts['staff'], residence: 'Unverified');
                $this->encounter($end->subHour(), 'Fever', $accounts['doctor'], $accounts['staff'], status: 'Cancelled');
                $this->encounter($end->subHours(49), 'Fever', $accounts['doctor'], $accounts['staff']);
                $engine = app(SurveillanceEngine::class);
                foreach ([3, 2, 1] as $weeksAgo) {
                    $engine->run('console', $end->subWeeks($weeksAgo));
                }
                $run = $engine->run('console', $end);
                $outcomes = $run->results->pluck('outcome', 'syndrome_name')->all();
                foreach (['SIMULATED Febrile' => 'Alert', 'SIMULATED Respiratory' => 'Watch', 'SIMULATED Gastrointestinal' => 'Normal', 'SIMULATED Rash - Unreviewed' => 'Insufficient Data'] as $name => $expected) {
                    if (($outcomes[$name] ?? null) !== $expected) {
                        throw new RuntimeException('The fictional scenario did not produce its expected detection outcome: '.$name);
                    }
                }
                $engine->run('console', $end);
                if (SurveillanceAlert::count() !== 1 || SurveillanceRun::where('status', 'Failed')->exists()) {
                    throw new RuntimeException('Fictional alert generation or duplicate prevention failed.');
                }
            });
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetUser();
            }
            config(['textbee.enabled' => $previousSms, 'mail.default' => $previousMailer]);
        }
    }

    private function encounter(CarbonImmutable $time, string $symptomName, User $doctor, User $staff, ?PatientInformation $patient = null, string $residence = 'Alabang', string $status = 'Completed'): PatientInformation
    {
        $date = $time->setTimezone('Asia/Manila')->toDateString();
        if ($patient === null) {
            $number = PatientInformation::count() + 1;
            $patient = PatientInformationFactory::new()->create([
                'first_name' => 'SIMULATED', 'middle_name' => null, 'last_name' => 'Patient '.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'address' => 'FICTIONAL DEMO ADDRESS - Barangay Alabang, Muntinlupa City', 'contact_number' => null, 'emergency_contact' => null,
                'sms_consent' => false, 'surveillance_residence' => $residence, 'registration_date' => $date,
            ]);
        }
        DB::table('queue_days')->insertOrIgnore(['queue_date' => $date, 'last_number' => 0]);
        $queue = Queue::create([
            'patient_id' => $patient->getKey(), 'queue_number' => (int) Queue::whereDate('queue_date', $date)->max('queue_number') + 1,
            'queue_date' => $date, 'status' => $status, 'assigned_by' => $staff->getKey(),
            'consulting_doctor_id' => $doctor->getKey(), 'completed_at' => $status === 'Completed' ? $time : null,
        ]);
        $vitals = VitalSignFactory::new()->create([
            'patient_id' => $patient->getKey(), 'queue_id' => $queue->getKey(), 'recorded_by' => $staff->getKey(), 'recorded_at' => $time,
            'temperature' => $symptomName === 'Fever' ? 38.1 : 36.7,
        ]);
        $record = MedicalRecordFactory::new()->create([
            'patient_id' => $patient->getKey(), 'queue_id' => $queue->getKey(), 'vital_signs_id' => $vitals->getKey(), 'handled_by' => $doctor->getKey(),
            'diagnosis' => 'SIMULATED assessment - software demonstration only', 'chief_complaint' => 'FICTIONAL patient reports '.$symptomName.'.',
            'consultation_date' => $date, 'encounter_at' => $time, 'created_at' => $time,
            'treatment' => 'SIMULATED care plan - not medical advice', 'consultation_notes' => 'FICTIONAL TEST RECORD. Never use for actual patient care.', 'follow_up_date' => null,
        ]);
        $symptom = Symptom::where('name', $symptomName)->firstOrFail();
        $record->symptoms()->attach($symptom->id, ['symptom_name' => $symptom->name]);

        return $patient;
    }
}
