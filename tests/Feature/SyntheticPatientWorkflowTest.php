<?php

use App\Livewire\DiagnosisManagement;
use App\Livewire\MedicalRecordDetails;
use App\Livewire\MedicalRecordsForm;
use App\Livewire\MedicalRecordsTable;
use App\Livewire\PatientInformationForm;
use App\Livewire\PatientTable;
use App\Livewire\QueueDashboard;
use App\Livewire\QueueDisplay;
use App\Livewire\QueueManagement;
use App\Livewire\VitalSignsForm;
use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\Symptom;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue as JobQueue;
use Livewire\Livewire;

it('processes fifteen registered synthetic patients and five repeat visits without mixing clinical history', function () {
    $this->get(route('patients.index'))->assertRedirect(route('login'));
    $this->travelTo(new DateTimeImmutable('2026-11-08 09:00:00 Asia/Manila'));
    Http::preventStrayRequests();
    Mail::fake();
    Notification::fake();
    JobQueue::fake();
    config(['textbee.enabled' => false]);

    $defaultConnection = DB::getDefaultConnection();
    $retainReview = filter_var(getenv('AHC_RETAIN_WORKFLOW_REVIEW'), FILTER_VALIDATE_BOOLEAN);
    $reviewSchema = null;
    $reviewManifest = null;

    try {
        if ($retainReview) {
            expect(app()->environment())->toBe('testing');
            $reviewSchema = 'ahc_synthetic_20261108_'.bin2hex(random_bytes(4));
            $serverConfig = array_replace(config('database.connections.mysql'), ['database' => null, 'url' => null]);
            config(['database.connections.synthetic_server' => $serverConfig]);
            DB::connection('synthetic_server')->statement("CREATE DATABASE `{$reviewSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            config(['database.connections.synthetic_review' => array_replace($serverConfig, ['database' => $reviewSchema])]);
            DB::setDefaultConnection('synthetic_review');
            $reviewManifest = storage_path('app/private/'.$reviewSchema.'.json');
            File::ensureDirectoryExists(dirname($reviewManifest));
            File::put($reviewManifest, json_encode(['schema' => $reviewSchema, 'result' => 'in progress', 'synthetic_only' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            expect(Artisan::call('migrate', ['--database' => 'synthetic_review', '--no-interaction' => true]))->toBe(0);
        }

        $staff = User::factory()->count(2)->sequence(
            ['name' => 'SYNTHETIC Staff One', 'username' => 'synthetic_staff_one', 'email' => 'staff.one@example.test'],
            ['name' => 'SYNTHETIC Staff Two', 'username' => 'synthetic_staff_two', 'email' => 'staff.two@example.test'],
        )->create(['role' => 'Local Health Unit Staff']);
        $doctors = User::factory()->count(2)->sequence(
            ['name' => 'SYNTHETIC Doctor One', 'username' => 'synthetic_doctor_one', 'email' => 'doctor.one@example.test'],
            ['name' => 'SYNTHETIC Doctor Two', 'username' => 'synthetic_doctor_two', 'email' => 'doctor.two@example.test'],
        )->create(['role' => 'Doctor']);
        Livewire::actingAs($doctors[0])->test(DiagnosisManagement::class)->call('createDiagnosis')
            ->set('name', 'SYNTHETIC routine review')->call('saveDiagnosis')->assertHasNoErrors();
        $catalogDiagnosis = Diagnosis::sole();

        $roster = [
            ['Amihan', 'Santos', 3, 100, 16, 16.0, 18, 18.0],
            ['Basilio', 'Reyes', 5, 110, 20, 16.5, 22, 18.2],
            ['Celia', 'Cruz', 7, 120, 24, 16.7, 26, 18.1],
            ['Dante', 'Garcia', 10, 130, 30, 17.8, 32, 18.9],
            ['Elisa', 'Mendoza', 13, 145, 40, 19.0, 42, 20.0],
            ['Felix', 'Ramos', 17, 155, 47, 19.6, null, null],
            ['Giselle', 'Aquino', 21, 160, 55, 21.5, null, null],
            ['Hector', 'Bautista', 28, 165, 60, 22.0, null, null],
            ['Isabel', 'Torres', 36, 170, 68, 23.5, null, null],
            ['Jaime', 'Flores', 42, 175, 77, 25.1, null, null],
            ['Karina', 'Navarro', 50, 150, 50, 22.2, null, null],
            ['Lorenzo', 'Castillo', 60, 158, 64, 25.6, null, null],
            ['Mila', 'Villanueva', 68, 162, 70, 26.7, null, null],
            ['Nestor', 'Mercado', 75, 168, 80, 28.3, null, null],
            ['Olivia', 'Del Rosario', 82, 172, 85, 28.7, null, null],
        ];
        $patients = collect();

        foreach ($roster as $index => [$firstName, $lastName, $age]) {
            $registration = Livewire::actingAs($staff[$index % 2])->test(PatientInformationForm::class);
            if ($index === 0) {
                $registration->call('savePatient')->assertHasErrors(['first_name', 'last_name', 'birthdate', 'address'])
                    ->assertSee('The first name field is required.');
            }
            $address = 'SYNTHETIC Unit '.($index + 1).', Barangay Alabang, Muntinlupa City, Metro Manila 1780';
            $registration->set([
                'first_name' => $firstName, 'middle_name' => $index % 3 === 0 ? 'Test' : '',
                'last_name' => 'SYNTHETIC '.$lastName,
                'birthdate' => now('Asia/Manila')->subYears($age)->toDateString(),
                'sex' => $index % 2 === 0 ? 'Female' : 'Male', 'address' => $address,
                'contact_number' => '0900000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'emergency_contact' => 'SYNTHETIC Guardian '.$lastName.', 09000000000',
            ])->assertSet('age', $age)->call('reviewDetails')->assertHasNoErrors()->assertSet('showReview', true)
                ->call('savePatient')->assertHasNoErrors()->assertDispatched('patient-information-saved');
            $patient = PatientInformation::findOrFail($registration->get('registeredPatientId'));
            expect($patient->address)->toBe($address);
            expect($patient->age)->toBe($age);
            $registration->call('savePatient')->assertHasNoErrors();
            $this->assertDatabaseCount('patient_information', $index + 1);
            $patients->push($patient);
        }

        $directory = Livewire::actingAs($staff[0])->test(PatientTable::class)->set('search', 'SYNTHETIC');
        expect($directory->get('patients')->total())->toBe(15);
        expect($directory->get('patients')->count())->toBe(10);
        $directory->call('nextPage');
        expect($directory->get('patients')->count())->toBe(5);
        $directory->set('sex', 'Female');
        expect($directory->get('patients')->total())->toBe(8);
        $directory->set('sex', '')->set('lastVisit', 'never');
        expect($directory->get('patients')->total())->toBe(15);
        $directory->call('resetFilters')->set('search', 'Amihan SYNTHETIC Santos')->call('viewPatient', $patients[0]->patient_id)
            ->assertSee('Patient Details')->assertSee($patients[0]->address)->call('closePatientModal');
        expect($directory->get('patients')->modelKeys())->toBe([$patients[0]->patient_id]);

        $visits = collect();
        foreach ($patients as $index => $patient) {
            $assignment = Livewire::actingAs($staff[$index % 2])->test(QueueManagement::class)
                ->call('selectPatient', $patient->patient_id);
            if ($index === 1) {
                $assignment->call('selectPatient', $patients[0]->patient_id)->call('addPatient')->assertHasErrors('queue')
                    ->assertSee('This patient already has an active queue entry today.')
                    ->assertSet('selectedPatientId', $patients[0]->patient_id)->call('selectPatient', $patient->patient_id);
            }
            $assignment->call('addPatient')->assertHasNoErrors()
                ->call('addPatient')->assertHasErrors('selectedPatientId');
            $visit = $patient->queues()->sole();
            expect($visit->queue_number)->toBe(1 + $index);
            expect($visit->assigned_by)->toBe($staff[$index % 2]->user_id);
            $visits->push($visit);
        }

        $display = Livewire::actingAs($staff[0])->test(QueueDisplay::class)->assertSee('wire:poll.2s.keep-alive', false)
            ->assertDontSee('SYNTHETIC')->assertDontSee('Amihan');
        expect($display->viewData('stages')['vital-signs']['waiting'])->toBe(range(1, 15));
        $manager = Livewire::actingAs($staff[0])->test(QueueManagement::class)->assertSee('wire:poll.10s', false)->set('status', 'Waiting');
        expect($manager->get('queues')->total())->toBe(15);
        $manager->call('nextPage');
        expect($manager->get('queues')->count())->toBe(5);
        $vitalObserver = Livewire::actingAs($staff[0])->test(VitalSignsForm::class)->assertSee('wire:poll.5s', false);
        $consultationObserver = Livewire::actingAs($doctors[0])->test(MedicalRecordsForm::class)->assertSee('wire:poll.5s', false);

        $allVisits = collect();
        for ($round = 1; $round <= 2; $round++) {
            if ($round === 2) {
                $this->travel(4)->hours();
            }
            $roundPatients = $round === 1 ? $patients : $patients->take(5);
            foreach ($roundPatients as $index => $patient) {
                $assignedStaff = $staff[($index + $round - 1) % 2];
                $doctor = $doctors[($index + $round - 1) % 2];
                if ($round === 2) {
                    Livewire::actingAs($assignedStaff)->test(QueueManagement::class)->call('selectPatient', $patient->patient_id)
                        ->call('addPatient')->assertHasNoErrors();
                }
                $visit = $patient->queues()->latest('queue_id')->firstOrFail();
                $number = $round === 1 ? 1 + $index : 16 + $index;
                $bloodPressure = (100 + $index + ($round - 1) * 10).'/'.(60 + $index);
                [$height, $weight, $bmi] = $round === 1 ? array_slice($roster[$index], 3, 3) : [$roster[$index][3], $roster[$index][6], $roster[$index][7]];

                Livewire::actingAs($assignedStaff)->test(QueueManagement::class)->call('changeStatus', $visit->queue_id, 'Waiting', 'Called')->assertHasNoErrors();
                $display->call('$refresh')->assertViewHas('stages', fn (array $stages): bool => in_array($number, array_column($stages['vital-signs']['serving'], 'number'), true));
                $vitals = Livewire::actingAs($assignedStaff)->test(VitalSignsForm::class)->call('selectQueue', $visit->queue_id, 'Called')
                    ->assertSet('patient_id', $patient->patient_id)->assertSee($patient->first_name)
                    ->set(['blood_pressure' => $bloodPressure, 'temperature' => 36.5, 'pulse_rate' => 80 + $index,
                        'respiratory_rate' => 18, 'height' => $height, 'weight' => $weight])->assertSet('bmi', $bmi);
                if ($index === 0 && $round === 1) {
                    $vitals->set('temperature', 99)->call('saveVitalSigns')->assertHasErrors('temperature')
                        ->assertSee('The temperature field must not be greater than 45.')->assertSet('blood_pressure', $bloodPressure)
                        ->assertSet('weight', $weight)->set('temperature', 36.5);
                    expect($visit->fresh()->status)->toBe('Vital Signs');
                    $this->assertDatabaseCount('vital_signs', 0);
                }
                $vitals->call('saveVitalSigns')->assertHasNoErrors()->assertDispatched('vital-signs-saved')
                    ->call('saveVitalSigns')->assertHasErrors('patient_id');
                $vital = $visit->vitalSign()->sole();
                expect((float) $vital->bmi)->toBe($bmi);
                expect($vital->recorded_by)->toBe($assignedStaff->user_id);
                expect($vital->patient_id)->toBe($patient->patient_id);
                expect($visit->fresh()->status)->toBe('Ready for Consultation');
                expect($visit->fresh()->queue_number)->toBe($number);
                $vitalObserver->call('$refresh');
                expect($vitalObserver->get('vitalSignsQueue')->modelKeys())->not->toContain($visit->queue_id);

                Livewire::actingAs($doctor);
                $consultationObserver->call('$refresh');
                expect($consultationObserver->get('consultationQueue')->modelKeys())->toContain($visit->queue_id);
                $display->call('$refresh')->assertViewHas('stages', fn (array $stages): bool => in_array($number, $stages['consultation']['waiting'], true));
                $consultation = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $visit->queue_id)
                    ->assertHasNoErrors()->assertSet('patient_id', $patient->patient_id)->assertSet('vital_signs_id', $vital->vital_id)
                    ->assertSee($bloodPressure)->assertSee($patient->address);
                expect($consultation->get('vitalSigns')->modelKeys())->toBe([$vital->vital_id]);
                if ($round === 2) {
                    $previous = $patient->medicalRecords()->oldest('medical_record_id')->firstOrFail();
                    $consultation->call('viewMedicalRecord', $previous->medical_record_id)->assertHasNoErrors();
                    expect($consultation->get('selectedMedicalRecord')->vital_signs_id)->toBe($previous->vital_signs_id);
                    expect($consultation->get('selectedMedicalRecord')->vitalSign->blood_pressure)->not->toBe($bloodPressure);
                    $consultation->call('closeMedicalRecordModal')->assertSet('vital_signs_id', $vital->vital_id);
                }
                $display->call('$refresh')->assertViewHas('stages', fn (array $stages): bool => in_array($number, array_column($stages['consultation']['serving'], 'number'), true));
                $complaint = "SYNTHETIC review: {$patient->first_name}, visit {$round}.";
                $consultation->set(['chief_complaint' => $complaint, 'treatment' => 'SYNTHETIC care plan; testing only.',
                    'consultation_notes' => "SYNTHETIC visit {$round}; queue {$number}.",
                    'prescription' => $index % 2 === 0 ? 'SYNTHETIC prescription; do not dispense.' : '',
                    'laboratory_requests' => $index % 3 === 0 ? 'SYNTHETIC lab request; testing only.' : '',
                    'follow_up_date' => $index % 4 === 0 ? '2026-11-15' : '',
                ])->call('selectSymptom', Symptom::where('name', 'Fever')->value('id'))
                    ->call('selectSymptom', Symptom::where('name', 'Headache')->value('id'));
                if ($index % 3 === 0) {
                    $consultation->set('diagnosis', "SYNTHETIC custom assessment {$patient->patient_id}/{$round}")
                        ->set('customSymptom', 'SYNTHETIC custom symptom')->call('addCustomSymptom');
                } else {
                    $consultation->call('selectDiagnosis', (string) $catalogDiagnosis->id);
                }
                if ($index === 0 && $round === 1) {
                    $consultation->set('treatment', '')->call('saveMedicalRecord')->assertHasErrors('treatment')
                        ->assertSee('The treatment field is required.')->assertSet('chief_complaint', $complaint)
                        ->set('treatment', 'SYNTHETIC care plan; testing only.');
                    expect($visit->fresh()->status)->toBe('In Consultation');
                    $this->assertDatabaseCount('medical_records', 0);
                }
                $consultation->call('saveMedicalRecord')->assertHasNoErrors()->assertDispatched('medical-record-saved')->assertDispatched('queue-updated')
                    ->call('saveMedicalRecord')->assertHasErrors('queueId');
                $record = $visit->medicalRecord()->sole();
                $this->assertDatabaseHas('medical_records', ['medical_record_id' => $record->medical_record_id,
                    'patient_id' => $patient->patient_id, 'queue_id' => $visit->queue_id, 'vital_signs_id' => $vital->vital_id,
                    'handled_by' => $doctor->user_id, 'chief_complaint' => $complaint]);
                expect($record->symptoms()->count())->toBe(2);
                expect($visit->fresh()->status)->toBe('Completed');
                expect($visit->fresh()->completed_at)->not->toBeNull();
                expect($visit->fresh()->queue_number)->toBe($number);
                expect($visit->fresh()->consulting_doctor_id)->toBe($doctor->user_id);
                $consultationObserver->call('$refresh');
                expect($consultationObserver->get('consultationQueue')->modelKeys())->not->toContain($visit->queue_id);
                $display->call('$refresh')->assertViewHas('stages', fn (array $stages): bool => ! in_array($number, array_merge(
                    $stages['vital-signs']['waiting'], array_column($stages['vital-signs']['serving'], 'number'),
                    $stages['consultation']['waiting'], array_column($stages['consultation']['serving'], 'number'),
                ), true));
                $allVisits->push(['patient_id' => $patient->patient_id, 'queue_id' => $visit->queue_id,
                    'queue_number' => $number, 'vital_id' => $vital->vital_id, 'medical_record_id' => $record->medical_record_id,
                    'recorded_by' => $assignedStaff->user_id, 'handled_by' => $doctor->user_id, 'blood_pressure' => $bloodPressure, 'bmi' => $bmi]);
            }
        }

        $this->assertDatabaseCount('patient_information', 15);
        $this->assertDatabaseCount('queues', 20);
        $this->assertDatabaseCount('vital_signs', 20);
        $this->assertDatabaseCount('medical_records', 20);
        $this->assertDatabaseCount('medical_record_symptoms', 40);
        expect(Queue::whereNotIn('status', Queue::TERMINAL_STATUSES)->count())->toBe(0);
        expect(Queue::distinct()->count('queue_number'))->toBe(20);

        foreach ($patients as $index => $patient) {
            $expectedVisits = $index < 5 ? 2 : 1;
            expect($patient->medicalRecords()->count())->toBe($expectedVisits);
            Livewire::actingAs($doctors[0])->test(MedicalRecordDetails::class, ['record' => $patient])
                ->assertViewHas('patient', fn (PatientInformation $loaded): bool => $loaded->medicalRecords->count() === $expectedVisits
                    && $loaded->medicalRecords->every(fn (MedicalRecord $record): bool => $record->patient_id === $patient->patient_id
                        && $record->vitalSign->queue_id === $record->queue_id && $record->vitalSign->patient_id === $patient->patient_id))
                ->assertSee("SYNTHETIC review: {$patient->first_name}, visit 1.");
        }
        $records = Livewire::actingAs($doctors[0])->test(MedicalRecordsTable::class);
        expect($records->get('patients')->total())->toBe(15);
        $records->call('nextPage');
        expect($records->get('patients')->count())->toBe(5);
        $records->set('search', 'Amihan SYNTHETIC Santos')->set('fromDate', '2026-11-08')->set('toDate', '2026-11-08');
        expect($records->get('patients')->total())->toBe(1);
        expect($records->get('patients')->first()->medical_records_count)->toBe(2);
        $records->set('fromDate', '2026-11-09')->set('toDate', '2026-11-09');
        expect($records->get('patients')->total())->toBe(0);
        $records->set('fromDate', 'not-a-date')->assertHasErrors('fromDate')->assertSee('The from date field must match the format Y-m-d.');

        $directory = Livewire::actingAs($staff[0])->test(PatientTable::class)->set('lastVisit', 'today')->set('recordStatus', 'Active');
        expect($directory->get('patients')->total())->toBe(15);
        $directory->set('lastVisit', 'never');
        expect($directory->get('patients')->total())->toBe(0);
        Livewire::actingAs($staff[0])->test(QueueManagement::class)->set('status', 'Completed')->set('search', 'Amihan SYNTHETIC Santos')
            ->assertSee('001')->assertSee('016');
        Livewire::actingAs($staff[0])->test(QueueDashboard::class)->call('$refresh')
            ->assertViewHas('statistics', fn (array $statistics): bool => $statistics['Total Patients Queued Today'] === 20
                && $statistics['Completed Patients'] === 20 && $statistics['Waiting Patients'] === 0);
        Livewire::actingAs($staff[0])->test(VitalSignsForm::class)->assertSee('No patients waiting for vital signs today.');
        Livewire::actingAs($doctors[0])->test(MedicalRecordsForm::class)->assertSee('No patients ready for consultation today.');
        Livewire::actingAs($staff[0])->test(QueueDisplay::class)->assertViewHas('stages', fn (array $stages): bool => $stages['vital-signs']['serving'] === [] && $stages['vital-signs']['waiting'] === []
            && $stages['consultation']['serving'] === [] && $stages['consultation']['waiting'] === []);

        Livewire::actingAs($doctors[0])->test(PatientInformationForm::class)->call('savePatient')->assertForbidden();
        Livewire::actingAs($doctors[0])->test(QueueManagement::class)->call('selectPatient', $patients[0]->patient_id)->assertForbidden();
        Livewire::actingAs($doctors[0])->test(VitalSignsForm::class)->assertForbidden();
        Livewire::actingAs($staff[0])->test(MedicalRecordsForm::class)->assertForbidden();
        Livewire::actingAs($staff[0])->test(DiagnosisManagement::class)->assertForbidden();
        $this->actingAs($staff[0])->get(route('doctors-consultation.index'))->assertForbidden();
        $this->actingAs($doctors[0])->get(route('patient.information-form'))->assertForbidden();
        $this->assertDatabaseCount('patient_information', 15);
        $this->assertDatabaseCount('medical_records', 20);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        JobQueue::assertNothingPushed();
        Http::assertNothingSent();

        if ($retainReview) {
            File::put($reviewManifest, json_encode([
                'schema' => $reviewSchema, 'result' => 'passed', 'synthetic_only' => true, 'patient_count' => 15, 'visit_count' => 20,
                'test_date_manila' => '2026-11-08', 'method' => 'Pest / actual Livewire actions and rendered views; no browser automation',
                'delivery' => 'SMS disabled, HTTP blocked, mail/notifications/jobs faked; nothing sent',
                'accounts' => $staff->merge($doctors)->map->only(['user_id', 'name', 'email', 'role'])->values()->all(),
                'test_account_password' => 'password',
                'patients' => $patients->map->only(['patient_id', 'first_name', 'last_name', 'age', 'sex', 'address'])->values()->all(),
                'visits' => $allVisits->all(), 'cleanup_sql' => "DROP DATABASE `{$reviewSchema}`;",
                'review' => 'Select this separate schema in your MySQL client. The application default database was not changed.',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    } finally {
        DB::setDefaultConnection($defaultConnection);
        DB::purge('synthetic_review');
        DB::purge('synthetic_server');
    }
});
