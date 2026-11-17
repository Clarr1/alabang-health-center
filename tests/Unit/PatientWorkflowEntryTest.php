<?php

use App\Livewire\MedicalRecordDetails;
use App\Livewire\MedicalRecordsForm;
use App\Livewire\PatientInformationForm;
use App\Livewire\QueueManagement;
use App\Livewire\VitalSignsForm;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\Symptom;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue as JobQueue;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

it('enters ten fictional patients through actual workflow actions and verifies completed visits', function () {
    $this->freezeTime();
    Http::preventStrayRequests();
    Mail::fake();
    Notification::fake();
    JobQueue::fake();
    config(['textbee.enabled' => false]);

    $retainEntries = filter_var(getenv('AHC_ENTER_LOCAL_PATIENTS'), FILTER_VALIDATE_BOOLEAN);
    $defaultConnection = DB::getDefaultConnection();
    $date = now('Asia/Manila')->toDateString();
    $batch = 'AHC-FICTIONAL-20261109';
    $reportPath = storage_path('app/private/'.strtolower($batch).'.json');

    if ($retainEntries) {
        expect(getenv('AHC_LOCAL_PATIENT_DATABASE'))->toBe('alabang-health-center');
        expect(config('database.connections.mysql.host'))->toBeIn(['localhost', '127.0.0.1', '::1']);
        config(['database.connections.patient_entry' => array_replace(config('database.connections.mysql'), [
            'database' => 'alabang-health-center', 'url' => null,
        ])]);
    } else {
        config(['database.connections.patient_entry' => array_replace(config('database.connections.sqlite'), [
            'database' => ':memory:', 'url' => null,
        ])]);
    }
    DB::setDefaultConnection('patient_entry');

    try {
        if (! $retainEntries) {
            expect(Artisan::call('migrate', ['--database' => 'patient_entry', '--no-interaction' => true]))->toBe(0);
            User::factory()->create(['role' => 'Local Health Unit Staff']);
            User::factory()->create(['role' => 'Doctor']);
        }
        $staff = User::where('is_active', true)->where('role', 'Local Health Unit Staff')->first()
            ?? User::where('is_active', true)->where('role', 'Admin')->first();
        $doctor = User::where('is_active', true)->where('role', 'Doctor')->first()
            ?? User::where('is_active', true)->where('role', 'Admin')->first();
        expect($staff)->not->toBeNull();
        expect($doctor)->not->toBeNull();
        expect(PatientInformation::where('address', 'like', '%'.$batch.'%')->count())->toBe(0);

        $samples = [
            ['name' => ['Alessandra', 'Reyes', 'Dalisay'], 'birthdate' => '1998-04-16', 'sex' => 'Female',
                'complaint' => 'Sneezing, clear runny nose, and itchy eyes for 3 days.', 'diagnosis' => 'Allergic rhinitis',
                'symptoms' => [], 'custom' => ['Sneezing', 'Runny nose', 'Itchy eyes'],
                'bp' => '110/70', 'temperature' => 36.6, 'pulse' => 76, 'respiration' => 16, 'height' => 160, 'weight' => 55, 'bmi' => 21.5,
                'treatment' => 'Avoid dust exposure; saline nasal irrigation and review if symptoms persist.', 'prescription' => 'Sample entry: cetirizine for allergy symptoms; not for dispensing.',
                'notes' => 'Afebrile, breathing comfortably, no wheeze. No known drug allergies.'],
            ['name' => ['Marco Luis', 'Villanueva', 'Sarmiento'], 'birthdate' => '1993-08-22', 'sex' => 'Male',
                'complaint' => 'Dry cough and sore throat for 2 days, with a low-grade fever.', 'diagnosis' => 'Acute viral upper respiratory infection',
                'symptoms' => ['Cough', 'Sore Throat', 'Fever'], 'custom' => [],
                'bp' => '118/76', 'temperature' => 37.8, 'pulse' => 88, 'respiration' => 18, 'height' => 170, 'weight' => 68, 'bmi' => 23.5,
                'treatment' => 'Rest, oral fluids, and symptomatic care; return for breathing difficulty or persistent fever.', 'prescription' => 'Sample entry: paracetamol as needed for fever; not for dispensing.',
                'notes' => 'Mild pharyngeal redness; lungs clear, no respiratory distress. No known drug allergies.'],
            ['name' => ['Bianca', 'Soriano', 'Velasco'], 'birthdate' => '1989-02-11', 'sex' => 'Female',
                'complaint' => 'Intermittent band-like headache after prolonged computer work for 1 week.', 'diagnosis' => 'Tension-type headache',
                'symptoms' => ['Headache'], 'custom' => [],
                'bp' => '116/74', 'temperature' => 36.5, 'pulse' => 74, 'respiration' => 16, 'height' => 165, 'weight' => 60, 'bmi' => 22.0,
                'treatment' => 'Regular screen breaks, hydration, sleep hygiene, and review for persistent symptoms.', 'prescription' => 'Sample entry: paracetamol as needed for headache; not for dispensing.',
                'notes' => 'Alert, normal neurologic screening. No sudden onset, vomiting, visual loss, or focal weakness.'],
            ['name' => ['Rafael', 'Mendoza', 'Alcantara'], 'birthdate' => '1984-06-05', 'sex' => 'Male',
                'complaint' => 'Lower back pain after lifting boxes yesterday.', 'diagnosis' => 'Acute mechanical low back pain',
                'symptoms' => ['Body Pain'], 'custom' => ['Lower back pain'],
                'bp' => '124/80', 'temperature' => 36.7, 'pulse' => 78, 'respiration' => 16, 'height' => 175, 'weight' => 77, 'bmi' => 25.1,
                'treatment' => 'Gentle activity, warm compresses, and lifting advice; return for weakness or bladder/bowel symptoms.', 'prescription' => 'Sample entry: paracetamol as needed for pain; not for dispensing.',
                'notes' => 'Localized lumbar muscle tenderness; gait and leg strength intact. No trauma, fever, or urinary symptoms.'],
            ['name' => ['Camille', 'Navarro', 'Manansala'], 'birthdate' => '2001-01-19', 'sex' => 'Female',
                'complaint' => 'Three episodes of watery diarrhea and mild abdominal cramps since this morning.', 'diagnosis' => 'Acute gastroenteritis without dehydration',
                'symptoms' => ['Diarrhea'], 'custom' => ['Abdominal cramps'],
                'bp' => '108/70', 'temperature' => 37.0, 'pulse' => 84, 'respiration' => 18, 'height' => 155, 'weight' => 47, 'bmi' => 19.6,
                'treatment' => 'Oral rehydration, continue food as tolerated, and review for worsening pain, blood in stool, or dehydration.', 'prescription' => 'Sample entry: oral rehydration salts; not for dispensing.',
                'notes' => 'Drinking normally; moist oral mucosa, abdomen soft, no guarding. No blood in stool or persistent vomiting.'],
            ['name' => ['Gabriel', 'Castro', 'Monteverde'], 'birthdate' => '1979-10-03', 'sex' => 'Male',
                'complaint' => 'Burning upper abdominal discomfort and sour taste after late meals for 2 weeks.', 'diagnosis' => 'Gastroesophageal reflux disease',
                'symptoms' => [], 'custom' => ['Heartburn', 'Sour regurgitation'],
                'bp' => '122/78', 'temperature' => 36.4, 'pulse' => 72, 'respiration' => 16, 'height' => 168, 'weight' => 70, 'bmi' => 24.8,
                'treatment' => 'Smaller meals, avoid lying down after meals, and review if symptoms persist or alarm symptoms develop.', 'prescription' => 'Sample entry: antacid for short-term symptom relief; not for dispensing.',
                'notes' => 'Abdomen soft, non-tender. No exertional chest pain, dysphagia, weight loss, black stool, or hematemesis.'],
            ['name' => ['Isabela Mae', 'Ramos', 'Buenaventura'], 'birthdate' => '2004-07-27', 'sex' => 'Female',
                'complaint' => 'Itchy red rash on both hands after using a new detergent for 4 days.', 'diagnosis' => 'Irritant contact dermatitis',
                'symptoms' => ['Rash'], 'custom' => ['Itching'],
                'bp' => '112/72', 'temperature' => 36.6, 'pulse' => 75, 'respiration' => 16, 'height' => 158, 'weight' => 52, 'bmi' => 20.8,
                'treatment' => 'Stop the suspected irritant, use protective gloves, and apply an unscented emollient.', 'prescription' => 'Sample entry: bland emollient for dry irritated skin; not for dispensing.',
                'notes' => 'Dry erythematous patches limited to hands; no pus, fever, facial swelling, or breathing difficulty.'],
            ['name' => ['Adrian', 'Bautista', 'Salcedo'], 'birthdate' => '1968-12-14', 'sex' => 'Male',
                'complaint' => 'Follow-up blood pressure check; no headache, chest pain, or shortness of breath.', 'diagnosis' => 'Essential hypertension, controlled on current treatment',
                'symptoms' => [], 'custom' => [],
                'bp' => '128/80', 'temperature' => 36.5, 'pulse' => 70, 'respiration' => 16, 'height' => 172, 'weight' => 74, 'bmi' => 25.0,
                'treatment' => 'Reinforce adherence, lower-salt diet, regular activity, and home blood pressure monitoring.', 'prescription' => 'Sample entry: continue previously prescribed maintenance medicine; no new prescription.',
                'notes' => 'Asymptomatic; reports adherence to established treatment. No edema or acute findings.'],
            ['name' => ['Nadine', 'Aquino', 'Lacsamana'], 'birthdate' => '1995-05-30', 'sex' => 'Female',
                'complaint' => 'Burning urination and urinary frequency for 2 days.', 'diagnosis' => 'Acute uncomplicated cystitis',
                'symptoms' => [], 'custom' => ['Painful urination', 'Urinary frequency'],
                'bp' => '114/72', 'temperature' => 36.9, 'pulse' => 82, 'respiration' => 16, 'height' => 162, 'weight' => 58, 'bmi' => 22.1,
                'treatment' => 'Oral hydration, urinalysis, and review of results; return promptly for fever, flank pain, or vomiting.', 'prescription' => 'No medicine prescribed in this fictional scenario; await sample urinalysis review.',
                'notes' => 'Mild suprapubic discomfort; no fever, flank tenderness, or systemic symptoms. Reports no pregnancy possibility.'],
            ['name' => ['Tomas Miguel', 'De Leon', 'Villareal'], 'birthdate' => '1962-03-08', 'sex' => 'Male',
                'complaint' => 'Gradual right knee pain while walking for 1 month, relieved by rest.', 'diagnosis' => 'Suspected right knee osteoarthritis',
                'symptoms' => ['Body Pain'], 'custom' => ['Right knee pain'],
                'bp' => '126/78', 'temperature' => 36.6, 'pulse' => 74, 'respiration' => 16, 'height' => 170, 'weight' => 72, 'bmi' => 24.9,
                'treatment' => 'Low-impact activity, quadriceps strengthening, pacing of walking, and follow-up for persistent limitation.', 'prescription' => 'Sample entry: paracetamol as needed for knee pain; not for dispensing.',
                'notes' => 'Mild crepitus, no hot swollen joint or recent injury. Able to bear weight; no fever.'],
        ];

        $baseline = ['patients' => PatientInformation::count(), 'queues' => Queue::count(), 'vitals' => VitalSign::count(), 'records' => MedicalRecord::count()];
        $summary = [];
        DB::beginTransaction();
        foreach ($samples as $index => $sample) {
            [$firstName, $middleName, $lastName] = $sample['name'];
            expect(PatientInformation::where('first_name', $firstName)->where('last_name', $lastName)->where('birthdate', $sample['birthdate'])->exists())->toBeFalse();
            $registration = Livewire::actingAs($staff)->test(PatientInformationForm::class)
                ->set([
                    'first_name' => $firstName, 'middle_name' => $middleName, 'last_name' => $lastName,
                    'birthdate' => $sample['birthdate'], 'sex' => $sample['sex'],
                    'address' => 'Fictional Unit '.($index + 1).', Barangay Alabang, Muntinlupa City 1780 [TEST DATA '.$batch.']',
                    'contact_number' => '0900000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'emergency_contact' => 'Fictional emergency contact: '.$middleName.' '.$lastName.', 09000000000',
                    'registration_date' => $date,
                ])->call('reviewDetails')->assertHasNoErrors()->call('savePatient')->assertHasNoErrors()->assertDispatched('patient-information-saved');
            $patientId = $registration->get('registeredPatientId');
            Livewire::actingAs($staff)->test(QueueManagement::class)->call('selectPatient', $patientId)
                ->call('addPatient')->assertHasNoErrors();
            $visit = Queue::where('patient_id', $patientId)->sole();
            $number = $visit->queue_number;
            Livewire::actingAs($staff)->test(VitalSignsForm::class)->call('selectQueue', $visit->queue_id, 'Waiting')->assertHasNoErrors()
                ->set(['blood_pressure' => $sample['bp'], 'temperature' => $sample['temperature'], 'pulse_rate' => $sample['pulse'],
                    'respiratory_rate' => $sample['respiration'], 'height' => $sample['height'], 'weight' => $sample['weight']])
                ->assertSet('bmi', $sample['bmi'])->call('saveVitalSigns')->assertHasNoErrors()->assertDispatched('vital-signs-saved');
            $vital = $visit->vitalSign()->sole();
            expect($visit->fresh()->status)->toBe('Ready for Consultation');
            $consultation = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $visit->queue_id)
                ->assertHasNoErrors()->assertSet('patient_id', $patientId)->assertSet('vital_signs_id', $vital->vital_id)->assertSee($sample['bp'])
                ->set(['chief_complaint' => $sample['complaint'], 'diagnosis' => $sample['diagnosis'],
                    'treatment' => $sample['treatment'], 'prescription' => $sample['prescription'],
                    'consultation_notes' => '[FICTIONAL TEST PATIENT '.$batch.'] '.$sample['notes'].' No real patient encounter; no medicines or tests to be dispensed.',
                    'consultation_date' => $date, 'follow_up_date' => now('Asia/Manila')->addWeek()->toDateString(),
                    'laboratory_requests' => $index === 8 ? 'Sample urinalysis with microscopy; fictional request only.' : 'No laboratory investigations indicated in this sample visit.',
                ]);
            foreach ($sample['symptoms'] as $name) {
                $symptom = Symptom::where('name', $name)->where('is_active', true)->firstOrFail();
                $consultation->call('selectSymptom', $symptom->id)->assertHasNoErrors();
            }
            foreach ($sample['custom'] as $name) {
                $consultation->set('customSymptom', $name)->call('addCustomSymptom')->assertHasNoErrors();
            }
            $consultation->call('saveMedicalRecord')->assertHasNoErrors()->assertDispatched('medical-record-saved');
            $record = $visit->medicalRecord()->sole();
            expect($visit->fresh()->status)->toBe('Completed');
            expect($visit->fresh()->completed_at)->not->toBeNull();
            expect($visit->fresh()->queue_number)->toBe($number);
            expect($visit->fresh()->assigned_by)->toBe($staff->user_id);
            expect($visit->fresh()->consulting_doctor_id)->toBe($doctor->user_id);
            expect($vital->recorded_by)->toBe($staff->user_id);
            expect($vital->patient_id)->toBe($patientId);
            expect($record->patient_id)->toBe($patientId);
            expect($record->vital_signs_id)->toBe($vital->vital_id);
            expect($record->handled_by)->toBe($doctor->user_id);
            expect($record->chief_complaint)->toBe($sample['complaint']);
            Livewire::actingAs($doctor)->test(MedicalRecordDetails::class, ['record' => PatientInformation::findOrFail($patientId)])
                ->assertSee($sample['complaint'])->assertSee($sample['diagnosis'])->assertSee($sample['bp']);
            $summary[] = ['name' => implode(' ', $sample['name']), 'patient_id' => $patientId, 'queue_number' => $number,
                'queue_id' => $visit->queue_id, 'vital_id' => $vital->vital_id, 'medical_record_id' => $record->medical_record_id,
                'chief_complaint' => $record->chief_complaint, 'diagnosis' => $record->diagnosis, 'status' => $visit->fresh()->status];
        }
        expect(PatientInformation::count())->toBe($baseline['patients'] + 10);
        expect(Queue::count())->toBe($baseline['queues'] + 10);
        expect(VitalSign::count())->toBe($baseline['vitals'] + 10);
        expect(MedicalRecord::count())->toBe($baseline['records'] + 10);
        expect(Queue::whereIn('queue_id', array_column($summary, 'queue_id'))->where('status', 'Completed')->count())->toBe(10);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        JobQueue::assertNothingPushed();
        Http::assertNothingSent();
        DB::commit();

        if ($retainEntries) {
            File::ensureDirectoryExists(dirname($reportPath));
            File::put($reportPath, json_encode(['batch' => $batch, 'database' => DB::connection()->getDatabaseName(), 'date_manila' => $date,
                'method' => 'Existing Livewire application actions; no browser automation', 'staff_id' => $staff->user_id,
                'doctor_id' => $doctor->user_id, 'patients' => $summary, 'delivery' => 'Nothing sent; mail, notifications and jobs faked; HTTP blocked.',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    } finally {
        while (DB::connection('patient_entry')->transactionLevel() > 0) {
            DB::connection('patient_entry')->rollBack();
        }
        DB::setDefaultConnection($defaultConnection);
        DB::purge('patient_entry');
    }
});
