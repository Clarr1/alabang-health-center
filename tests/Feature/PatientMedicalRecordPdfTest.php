<?php

use App\Models\FollowUp;
use App\Models\Household;
use App\Models\ImmunizationDose;
use App\Models\MedicalRecord;
use App\Models\PatientCondition;
use App\Models\PatientInformation;
use App\Models\PatientProfile;
use App\Models\Queue;
use App\Models\Symptom;
use App\Models\User;
use App\Services\PatientMedicalRecordPdf;
use Database\Factories\MedicalRecordFactory;
use Database\Factories\VitalSignFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-18 09:30:00 Asia/Manila'));
    $this->staff = User::factory()->create(['role' => 'Doctor', 'name' => 'Dr. Export Example']);
    $this->actingAs($this->staff);
});

/** @param array<string, mixed> $attributes */
function createVisitForPdf(PatientInformation $patient, array $attributes = []): MedicalRecord
{
    $vital = VitalSignFactory::new()->create(['patient_id' => $patient->getKey()]);

    return MedicalRecordFactory::new()->create(array_replace([
        'patient_id' => $patient->getKey(), 'vital_signs_id' => $vital->getKey(),
    ], $attributes));
}

/** @return array{patient: int, record: int} */
function visitPdfParameters(MedicalRecord $record): array
{
    return ['patient' => $record->patient_id, 'record' => $record->getKey()];
}

/** @return array<int, string> */
function medicalRecordPdfStreams(string $pdf): array
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
    $pages = [];
    foreach ($streams[1] as $stream) {
        $content = @gzuncompress($stream);
        if ($content !== false && preg_match('/BT [-\d.]+ [-\d.]+ Td/', $content) === 1) {
            $pages[] = $content;
        }
    }

    return $pages;
}

it('exports only the selected consultation ID even for multiple visits on the same day', function () {
    $patient = PatientInformation::factory()->create(['household_id' => Household::factory()]);
    $records = [];
    foreach (['2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-17'] as $index => $date) {
        $records[] = createVisitForPdf($patient, ['consultation_date' => $date, 'diagnosis' => 'VisitOnlyMarker'.$index]);
    }
    $otherPatient = PatientInformation::factory()->create(['household_id' => $patient->household_id]);
    createVisitForPdf($otherPatient, ['diagnosis' => 'OtherPatientSecret']);
    PatientProfile::factory()->create(['patient_id' => $patient->getKey(), 'pertinent_information' => 'UnrelatedRegistrationHistory']);
    PatientCondition::factory()->create(['patient_id' => $patient->getKey(), 'name' => 'UnrelatedCondition']);
    ImmunizationDose::factory()->create(['patient_id' => $patient->getKey(), 'vaccine_name' => 'UnrelatedVaccine']);
    FollowUp::create(['patient_id' => $patient->getKey(), 'scheduled_at' => now()->addDay(), 'purpose' => 'UnrelatedFollowUp', 'mobile' => '09171234567', 'created_by' => $this->staff->getKey()]);
    $tables = ['patient_information', 'medical_records', 'vital_signs', 'follow_ups', 'patient_profiles', 'patient_conditions', 'immunization_doses'];
    $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->get()->toJson()]);

    foreach ($records as $index => $record) {
        $data = app(PatientMedicalRecordPdf::class)->data($record);
        expect($data['record']->getKey())->toBe($record->getKey());
        $html = view('medical-records.pdf', $data)->render();
        $response = $this->get(route('medical-records.download', visitPdfParameters($record)))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $text = implode('', medicalRecordPdfStreams($response->getContent()));
        foreach ($records as $otherIndex => $otherRecord) {
            expect(str_contains($html, 'VisitOnlyMarker'.$otherIndex))->toBe($index === $otherIndex);
            expect(str_contains($text, mb_convert_encoding('VisitOnlyMarker'.$otherIndex, 'UTF-16BE', 'UTF-8')))->toBe($index === $otherIndex);
        }
        foreach (['OtherPatientSecret', 'UnrelatedRegistrationHistory', 'UnrelatedCondition', 'UnrelatedVaccine', 'UnrelatedFollowUp'] as $excluded) {
            expect($html)->not->toContain($excluded);
            expect(str_contains($text, mb_convert_encoding($excluded, 'UTF-16BE', 'UTF-8')))->toBeFalse();
        }
    }
    foreach ($before as $table => $rows) {
        expect(DB::table($table)->get()->toJson())->toBe($rows);
    }
    expect(DB::table('clinical_record_audits')->where('action', 'medical-records-pdf-generated')->count())->toBe(5);
    $details = json_decode(DB::table('clinical_record_audits')->latest('id')->value('details'), true);
    expect($details['medical_record_id'])->toBe($records[4]->getKey());
});

it('includes complete selected visit findings linked measurements follow-up and age at that visit', function () {
    $patient = PatientInformation::factory()->create([
        'first_name' => 'Maria', 'middle_name' => 'Elena', 'last_name' => 'Santos',
        'birthdate' => '2006-11-18', 'household_id' => Household::factory(['family_number' => 'FN-EXPORT-001']),
    ]);
    $queue = Queue::factory()->create(['patient_id' => $patient->getKey(), 'status' => 'Completed']);
    $record = createVisitForPdf($patient, [
        'queue_id' => $queue->getKey(), 'handled_by' => $this->staff->getKey(),
        'consultation_date' => '2026-11-17', 'encounter_at' => '2026-11-17 01:15:00',
        'chief_complaint' => 'Selected headache', 'past_medical_history' => 'History recorded during this visit',
        'physical_examination' => ['skin' => ['findings' => ['Good Skin Turgor'], 'other' => 'Selected exam finding']],
        'diagnosis' => 'Selected diagnosis', 'prescription' => 'Selected medication', 'treatment' => 'Selected treatment',
        'consultation_notes' => '<script>alert("clinical text")</script>', 'custom_symptoms' => ['Selected custom symptom'],
        'laboratory_results' => 'Selected lab results', 'referrals' => 'Selected referral', 'follow_up_instructions' => 'Selected instructions',
    ]);
    $record->vitalSign->update(['queue_id' => $queue->getKey(), 'blood_pressure' => '110/70', 'pulse_rate' => 0, 'nutritional_status' => 'Normal']);
    $record->symptoms()->attach(Symptom::factory()->create(), ['symptom_name' => 'Selected saved symptom']);
    FollowUp::create(['patient_id' => $patient->getKey(), 'medical_record_id' => $record->getKey(), 'scheduled_at' => now()->addDay(), 'purpose' => 'Selected follow-up', 'internal_notes' => 'Selected staff notes', 'mobile' => '09171234567', 'created_by' => $this->staff->getKey()]);
    $data = app(PatientMedicalRecordPdf::class)->data($record);
    expect($data['ageAtVisit'])->toBe(19)->and($patient->age)->toBe(20);
    expect(view('medical-records.pdf', $data)->render())
        ->toContain('Maria Elena Santos', 'FN-EXPORT-001', 'Age at visit', '19 years', '17 Nov 2026, 09:15 AM', 'Selected headache', 'History recorded during this visit', 'Selected exam finding', 'Selected diagnosis', 'Selected medication', 'Selected treatment', 'Selected custom symptom', 'Selected saved symptom', 'Selected lab results', 'Selected referral', 'Selected instructions', 'Selected follow-up', 'Selected staff notes', '110/70', '0 bpm', 'Normal', 'Dr. Export Example', '&lt;script&gt;')
        ->not->toContain('20 years', '<script>', 'Patient ID');
});

it('allows individual visit printing for the existing medical record viewing roles', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $record = createVisitForPdf(PatientInformation::factory()->create());
    $this->get(route('medical-records.print', visitPdfParameters($record)))->assertOk();
    $this->get(route('medical-records.download', visitPdfParameters($record)))->assertOk()->assertHeader('Content-Type', 'application/pdf');
})->with(['Admin', 'Doctor', 'Local Health Unit Staff']);

it('protects individual PDF download viewing and printing from unauthorized users', function (string $route) {
    $record = createVisitForPdf(PatientInformation::factory()->create());
    $url = route($route, visitPdfParameters($record));
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
    $this->staff->role = 'Unassigned';
    $this->actingAs($this->staff)->get($url)->assertForbidden();
    $this->staff->role = 'Doctor';
    $this->staff->is_active = false;
    $this->actingAs($this->staff)->get($url)->assertForbidden();
    expect(DB::table('clinical_record_audits')->where('action', 'medical-records-pdf-generated')->count())->toBe(0);
})->with(['medical-records.download', 'medical-records.pdf', 'medical-records.print']);

it('rejects mismatched patients family contexts and missing visits for every export action', function (string $route) {
    $patient = PatientInformation::factory()->create(['household_id' => Household::factory()]);
    $record = createVisitForPdf($patient);
    $otherPatient = PatientInformation::factory()->create(['household_id' => $patient->household_id]);
    $otherFamily = Household::factory()->create();
    $parameters = visitPdfParameters($record);
    $this->get(route($route, array_replace($parameters, ['patient' => $otherPatient->getKey()])))->assertNotFound();
    $this->get(route($route, [...$parameters, 'familyHouseholdId' => $otherFamily->getKey()]))->assertNotFound();
    $this->get(route($route, array_replace($parameters, ['record' => 999999999])))->assertNotFound();
    $this->get(route($route, array_replace($parameters, ['patient' => 999999999])))->assertNotFound();
})->with(['medical-records.download', 'medical-records.pdf', 'medical-records.print']);

it('places a distinct print link on every visit and preserves the selected ID through printing and download', function () {
    $patient = PatientInformation::factory()->create(['household_id' => Household::factory()]);
    $records = [createVisitForPdf($patient), createVisitForPdf($patient)];
    $response = $this->get(route('medical-records.show', $patient))->assertOk()->assertDontSee('Print Medical Records');
    $family = $this->get(route('families.members.show', ['record' => $patient, 'familyHouseholdId' => $patient->household_id]))->assertOk();
    foreach ($records as $record) {
        $parameters = visitPdfParameters($record);
        $response->assertSeeHtml('href="'.route('medical-records.print', $parameters).'"');
        $family->assertSee(route('medical-records.print', [...$parameters, 'familyHouseholdId' => $patient->household_id]));
        $this->get(route('medical-records.print', $parameters))->assertOk()
            ->assertSee(route('medical-records.download', $parameters))
            ->assertSee('application/pdf')->assertSee("credentials: 'same-origin'", false)
            ->assertSee('frame.contentWindow.print()', false)->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
    expect(substr_count($response->getContent(), '>Print Medical Record</a>'))->toBe(2);
    $this->get('/medical-records/records/'.$patient->getKey().'/pdf')->assertNotFound();
});

it('does not substitute measurements or follow-ups belonging to another patient or queue', function (bool $wrongPatient) {
    $patient = PatientInformation::factory()->create();
    $record = createVisitForPdf($patient);
    $otherPatient = PatientInformation::factory()->create();
    $queue = Queue::factory()->create(['patient_id' => $patient->getKey(), 'status' => 'Completed']);
    $otherQueue = Queue::factory()->create(['patient_id' => $patient->getKey(), 'status' => 'Completed']);
    $record->update(['queue_id' => $queue->getKey()]);
    $record->vitalSign->update(['patient_id' => $wrongPatient ? $otherPatient->getKey() : $patient->getKey(), 'queue_id' => $wrongPatient ? $queue->getKey() : $otherQueue->getKey(), 'blood_pressure' => '190/99']);
    VitalSignFactory::new()->create(['patient_id' => $patient->getKey(), 'blood_pressure' => '180/88']);
    FollowUp::create(['patient_id' => $otherPatient->getKey(), 'medical_record_id' => $record->getKey(), 'scheduled_at' => now()->addDay(), 'purpose' => 'Mislinked follow-up', 'mobile' => '09171234567', 'created_by' => $this->staff->getKey()]);
    $data = app(PatientMedicalRecordPdf::class)->data($record);
    expect($data['record']->vitalSign)->toBeNull()->and($data['record']->followUp)->toBeNull();
    expect(view('medical-records.pdf', $data)->render())->toContain('No vital signs linked to this visit.')
        ->not->toContain('190/99', '180/88', 'Mislinked follow-up');
})->with([true, false]);

it('renders a limited or extensive single visit as a private A4 PDF with numbered pages', function (bool $extensive) {
    $patient = PatientInformation::factory()->create(['first_name' => 'Synthetic', 'middle_name' => null, 'last_name' => 'Visit Example']);
    $record = createVisitForPdf($patient, [
        'handled_by' => $this->staff->getKey(), 'diagnosis' => 'Synthetic selected visit',
        'consultation_date' => '2026-11-17',
        'consultation_notes' => $extensive ? str_repeat('Documented clinical narrative with details for page wrapping. ', 150).' END-OF-SELECTED-VISIT' : null,
    ]);
    $record->vitalSign->update(['recorded_at' => '2026-11-17 01:00:00']);
    if (! $extensive) {
        $record->update(['follow_up_date' => null]);
        $record->vitalSign->update(['blood_pressure' => null, 'temperature' => null, 'height' => null, 'weight' => null, 'bmi' => null]);
    }
    $parameters = visitPdfParameters($record);
    $response = $this->get(route('medical-records.download', $parameters))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=medical-record-'.$record->getKey().'.pdf')
        ->assertHeader('Pragma', 'no-cache')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    $pdf = $response->getContent();
    expect(str_starts_with($pdf, '%PDF-'))->toBeTrue();
    preg_match('/\/MediaBox\s*\[0\.\d+ 0\.\d+ ([\d.]+) ([\d.]+)\]/', $pdf, $size);
    expect((float) $size[1])->toEqualWithDelta(595.276, 0.1)->and((float) $size[2])->toEqualWithDelta(841.890, 0.1);
    preg_match('/\/Type\s*\/Pages\b[^>]*\/Count\s+(\d+)/s', $pdf, $matches);
    $pages = medicalRecordPdfStreams($pdf);
    expect($pages)->toHaveCount((int) $matches[1]);
    expect(count($pages))->toBeGreaterThanOrEqual($extensive ? 3 : 1);
    foreach ($pages as $index => $content) {
        foreach (['Alabang Health Center', 'Family Number: Not assigned', 'Page '.($index + 1).' of '.count($pages)] as $text) {
            expect(str_contains($content, mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')))->toBeTrue();
        }
    }
    $this->get(route('medical-records.pdf', $parameters))->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=medical-record-'.$record->getKey().'.pdf');
    if (getenv('MEDICAL_RECORD_PDF_PREVIEW') === '1') {
        $directory = storage_path('framework/testing/pdf-previews');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/'.($extensive ? 'extensive-visit' : 'limited-visit').'.pdf', $pdf);
    }
})->with([false, true]);
