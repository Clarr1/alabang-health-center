<?php

namespace Database\Seeders;

use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\SurveillanceAudit;
use App\Models\SurveillanceSetting;
use App\Models\Symptom;
use App\Models\SyndromeCategory;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\PatientCategories;
use App\Services\SurveillanceAdministration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SyndromicEarlyWarningSeeder extends Seeder
{
    public const MARKER = 'Syndromic early warning fixture v1';

    public const PREFIX = 'SEW TEST';

    /**
     * Fixture dates are anchored on the first successful run. Subsequent runs are
     * read only; use the printed replay commands when the current window expires.
     * Test definitions exercise the existing engine and are not clinical approvals.
     * Historical spikes stay in their final 18 hours to avoid contaminating the
     * latest matching-weekday baseline. The combined spike spans both current days.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Syndromic test data is permitted only in local or testing environments.');
        }

        $lock = Cache::lock('syndromic-early-warning-seeder', 3600);
        if (! $lock->get()) {
            throw new RuntimeException('The syndromic test seeder is already running.');
        }
        $previousUser = Auth::user();
        $previousSms = config('textbee.enabled');
        $previousMailer = config('mail.default');
        config(['textbee.enabled' => false, 'mail.default' => 'array']);

        try {
            $manifest = DB::transaction(function (): array {
                $existing = SurveillanceAudit::where('action', self::MARKER)->first();
                if ($existing !== null) {
                    return $existing->details;
                }

                $end = CarbonImmutable::now('UTC')->startOfMinute();
                $settings = SurveillanceSetting::current();
                $days = max(60, (int) $settings['baseline_weeks'] * 7 + 10);
                $dailyAttendance = max(20, (int) ceil($settings['minimum_attendance'] / 2));
                $start = $end->subDays($days)->setTimezone('Asia/Manila')->startOfDay()->utc();
                $admin = $this->account('admin', 'Admin');
                $doctor = $this->account('doctor', 'Doctor');
                $staff = $this->account('staff', 'Local Health Unit Staff');
                Auth::setUser($admin);
                $scenarios = $this->scenarios();
                $catalog = [];
                foreach (array_unique(array_merge(...array_column($scenarios, 'symptoms'))) as $name) {
                    $symptom = Symptom::firstOrCreate(['name' => $name], [
                        'description' => self::PREFIX.' catalog entry for fictional software testing.',
                        'is_active' => true,
                    ]);
                    if (! $symptom->is_active) {
                        throw new RuntimeException('Required symptom is inactive: '.$name.'. No existing catalog entries were changed.');
                    }
                    $catalog[$name] = $symptom;
                }
                $administration = app(SurveillanceAdministration::class);
                foreach ($scenarios as $key => $scenario) {
                    $name = self::PREFIX.' '.$scenario['name'];
                    if (SyndromeCategory::where('name', $name)->exists()) {
                        throw new RuntimeException('Test definition name is already in use: '.$name);
                    }
                    $rule = $administration->saveDefinition([
                        'name' => $name, 'description' => 'FICTIONAL software fixture. Not a validated clinical case definition.',
                        'is_active' => true, 'symptom_ids' => array_map(fn (string $name): int => $catalog[$name]->id, $scenario['symptoms']),
                        'minimum_matches' => count($scenario['symptoms']),
                        'applies_from' => $start->setTimezone('Asia/Manila')->toDateString(),
                        'rationale' => 'SIMULATED approval by a fictional test account for local software testing only. No clinical approval is implied.',
                    ]);
                    $administration->approve($rule->id);
                    $scenarios[$key]['diagnosis'] = Diagnosis::firstOrCreate(['normalized_name' => Diagnosis::normalizeName($name)], [
                        'name' => $name, 'description' => 'Fictional syndromic assessment; disease is not laboratory confirmed.',
                        'is_active' => true, 'review_status' => 'approved',
                    ]);
                }
                if (! $settings['settings_reviewed']) {
                    $administration->saveSettings(array_replace($settings, ['settings_reviewed' => true]));
                }

                $number = 0;
                for ($day = $days - 1; $day >= 0; $day--) {
                    for ($index = 0; $index < $dailyAttendance; $index++) {
                        $key = match ($index) {
                            0 => 'dengue', 1 => 'chickenpox', 2 => 'malaria', 3 => 'control',
                            4 => $day % 3 === 0 ? 'control' : 'background', default => 'background',
                        };
                        $this->encounter($end->subDays($day)->subHours(2)->subMinutes($index), $scenarios[$key], $catalog, $doctor, $staff, ++$number);
                    }
                }

                $spikeCount = max(24, $dailyAttendance * 2);
                $replays = ['Normal baseline' => $end->subDays(8), 'No outbreak' => $end->subDays(7)->subHours(12)];
                foreach (['dengue' => 6, 'chickenpox' => 4, 'malaria' => 2] as $key => $daysAgo) {
                    $scenarioEnd = $end->subDays($daysAgo);
                    $replays[$scenarios[$key]['name']] = $scenarioEnd;
                    for ($index = 0; $index < $spikeCount; $index++) {
                        $this->encounter($scenarioEnd->subHours($index % 2 === 0 ? 6 : 18)->subMinutes(intdiv($index, 2)), $scenarios[$key], $catalog, $doctor, $staff, ++$number);
                    }
                }
                $repeatPatient = null;
                foreach (['dengue', 'chickenpox', 'malaria'] as $key) {
                    for ($index = 0; $index < $spikeCount; $index++) {
                        $patient = $this->encounter($end->subHours($index % 2 === 0 ? 6 : 30)->subMinutes(intdiv($index, 2)), $scenarios[$key], $catalog, $doctor, $staff, ++$number);
                        $repeatPatient ??= $patient;
                    }
                }
                $this->encounter($end->subHours(48), $scenarios['dengue'], $catalog, $doctor, $staff, ++$number);
                $this->encounter($end->subHours(49), $scenarios['dengue'], $catalog, $doctor, $staff, ++$number);
                $this->encounter($end->subHour(), $scenarios['dengue'], $catalog, $doctor, $staff, $number, $repeatPatient);
                $replays['Combined current spike'] = $end;
                $manifest = [
                    'fixture' => self::PREFIX, 'anchor' => $end->format('Y-m-d\TH:i:s\Z'),
                    'history_days' => $days, 'patients' => $number, 'consultations' => $number + 1,
                    'daily_attendance' => $dailyAttendance, 'spike_cases' => $spikeCount,
                    'history_start' => $end->subDays($days - 1)->subHours(2)->subMinutes($dailyAttendance - 1)->toIso8601String(),
                    'settings' => $settings,
                    'windows' => collect($replays)->map(fn (CarbonImmutable $time): array => [
                        'start' => $time->subHours(48)->format('Y-m-d\TH:i:s\Z'), 'end' => $time->format('Y-m-d\TH:i:s\Z'),
                    ])->all(),
                ];
                SurveillanceAudit::create(['actor_id' => $admin->getKey(), 'action' => self::MARKER, 'details' => $manifest]);

                return $manifest;
            });

            $this->command?->info("SEW TEST fixture: {$manifest['patients']} patients, {$manifest['consultations']} consultations over {$manifest['history_days']} days. Reruns preserve this dataset.");
            foreach ($manifest['windows'] as $name => $window) {
                $this->command?->line($name.' ['.$window['start'].', '.$window['end'].'): php artisan surveillance:detect --at='.$window['end']);
            }
            $this->command?->info('No alerts were inserted. Run the replay commands in order to calculate history and alerts, then surveillance:detect for the current window.');
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetUser();
            }
            config(['textbee.enabled' => $previousSms, 'mail.default' => $previousMailer]);
            $lock->release();
        }
    }

    private function account(string $key, string $role): User
    {
        $email = 'sew-test-'.$key.'@example.test';
        if (User::where('email', $email)->orWhere('username', 'sew_test_'.$key)->exists()) {
            throw new RuntimeException('Reserved fictional account already exists: '.$email);
        }

        return User::factory()->create([
            'first_name' => self::PREFIX, 'last_name' => ucfirst($key), 'middle_name' => null,
            'name' => self::PREFIX.' '.ucfirst($key), 'email' => $email, 'username' => 'sew_test_'.$key,
            'role' => $role, 'password' => Str::password(40), 'remember_token' => null,
        ]);
    }

    /** @return array<string, array{name: string, symptoms: list<string>, complaint: string}> */
    private function scenarios(): array
    {
        return [
            'dengue' => ['name' => 'Dengue-like', 'symptoms' => ['Fever', 'Headache', 'Body Pain'], 'complaint' => 'Fever for two days with headache and joint and muscle aches.'],
            'chickenpox' => ['name' => 'Chickenpox-like', 'symptoms' => ['Fever', 'Rash'], 'complaint' => 'Fever followed by an itchy blister-like skin rash since yesterday.'],
            'malaria' => ['name' => 'Malaria-like', 'symptoms' => ['Fever', 'Chills', 'Sweating'], 'complaint' => 'Intermittent fever with shaking chills followed by sweating for two days.'],
            'control' => ['name' => 'No-outbreak respiratory control', 'symptoms' => ['Cough', 'Sore Throat'], 'complaint' => 'Mild cough and sore throat for three days without fever.'],
            'background' => ['name' => 'Background headache', 'symptoms' => ['Headache'], 'complaint' => 'Mild headache after work without fever, rash, or body aches.'],
        ];
    }

    /**
     * @param  array{name: string, symptoms: list<string>, complaint: string, diagnosis: Diagnosis}  $scenario
     * @param  array<string, Symptom>  $catalog
     */
    private function encounter(CarbonImmutable $time, array $scenario, array $catalog, User $doctor, User $staff, int $number, ?PatientInformation $patient = null): PatientInformation
    {
        $date = $time->setTimezone('Asia/Manila')->toDateString();
        if ($patient === null) {
            $birthdate = $time->subYears(18 + $number % 50)->subMonths($number % 12)->toDateString();
            $patient = PatientInformation::create([
                'first_name' => self::PREFIX, 'middle_name' => null, 'last_name' => 'Patient '.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'birthdate' => $birthdate, 'age' => (int) CarbonImmutable::parse($birthdate)->diffInYears($time),
                'sex' => $number % 2 === 0 ? 'Female' : 'Male',
                'address' => 'FICTIONAL TEST ADDRESS '.$number.' - Barangay Alabang, Muntinlupa City',
                'surveillance_residence' => 'Alabang', 'contact_number' => null, 'emergency_contact' => null,
                'sms_consent' => false, 'registration_date' => $date, 'status' => 'Active',
                'patient_category' => app(PatientCategories::class)->suggest($birthdate),
                'created_at' => $time->subHour(), 'updated_at' => $time->subHour(),
            ]);
            $patient->forceFill(['created_at' => $time->subHour(), 'updated_at' => $time->subHour(), 'residence_verified_at' => $time->subHour()])->save();
            $patient->registrationProfile()->create([
                'prepared_by' => $staff->getKey(), 'completed_at' => $time->subHour(),
                'pertinent_information' => 'FICTIONAL TEST RECORD. Never use for actual patient care.',
                'created_at' => $time->subHour(), 'updated_at' => $time->subHour(),
            ]);
        }
        DB::table('queue_days')->insertOrIgnore(['queue_date' => $date, 'last_number' => 0]);
        $day = DB::table('queue_days')->where('queue_date', $date)->lockForUpdate()->first();
        $queueNumber = max($day->last_number, (int) Queue::whereDate('queue_date', $date)->max('queue_number')) + 1;
        $queue = Queue::create([
            'patient_id' => $patient->getKey(), 'queue_date' => $date, 'queue_number' => $queueNumber,
            'status' => 'Completed', 'assigned_by' => $staff->getKey(), 'consulting_doctor_id' => $doctor->getKey(),
            'called_at' => $time->addMinutes(5), 'completed_at' => $time->addMinutes(30),
        ]);
        $queue->forceFill(['created_at' => $time, 'updated_at' => $time->addMinutes(30)])->save();
        DB::table('queue_days')->where('queue_date', $date)->update(['last_number' => $queueNumber]);
        $febrile = in_array('Fever', $scenario['symptoms'], true);
        $vitals = VitalSign::create([
            'patient_id' => $patient->getKey(), 'queue_id' => $queue->getKey(), 'recorded_by' => $staff->getKey(),
            'blood_pressure' => '118/78', 'temperature' => $febrile ? 38.2 + $number % 8 / 10 : 36.7,
            'pulse_rate' => $febrile ? 98 : 76, 'respiratory_rate' => 18, 'height' => 160 + $number % 15,
            'weight' => 55 + $number % 15, 'oxygen_saturation' => 98, 'recorded_at' => $time->addMinutes(10),
        ]);
        $vitals->forceFill(['created_at' => $time->addMinutes(10), 'updated_at' => $time->addMinutes(10)])->save();
        $record = MedicalRecord::create([
            'patient_id' => $patient->getKey(), 'queue_id' => $queue->getKey(), 'vital_signs_id' => $vitals->getKey(),
            'handled_by' => $doctor->getKey(), 'consultation_date' => $date, 'encounter_at' => $time, 'created_at' => $time->addMinutes(30),
            'diagnosis_id' => $scenario['diagnosis']->id, 'diagnosis' => $scenario['diagnosis']->name,
            'chief_complaint' => $scenario['complaint'], 'custom_symptoms' => [],
            'assessment_notes' => 'SIMULATED syndromic assessment; no confirmed infectious disease.',
            'treatment' => 'FICTIONAL care plan: hydration, symptom monitoring, and clinical reassessment.',
            'consultation_notes' => 'FICTIONAL TEST RECORD. '.self::PREFIX.'. Never use for actual patient care.',
            'follow_up_date' => null,
        ]);
        $record->symptoms()->attach(collect($scenario['symptoms'])->mapWithKeys(fn (string $name): array => [$catalog[$name]->id => ['symptom_name' => $catalog[$name]->name]])->all());

        return $patient;
    }
}
