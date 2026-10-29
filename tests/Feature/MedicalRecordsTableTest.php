<?php

use App\Livewire\MedicalRecordDetails;
use App\Livewire\MedicalRecordsForm;
use App\Livewire\MedicalRecordsTable;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function savedRecordForBrowsing(User $doctor, ?PatientInformation $patient = null, string $date = '2026-10-14'): MedicalRecord
{
    $patient ??= PatientInformation::factory()->create();
    $vital = VitalSign::create([
        'patient_id' => $patient->patient_id, 'recorded_by' => $doctor->user_id,
        'blood_pressure' => '120/80', 'temperature' => 36.5, 'pulse_rate' => 72,
        'respiratory_rate' => 16, 'recorded_at' => now(),
    ]);

    return MedicalRecord::create([
        'patient_id' => $patient->patient_id, 'vital_signs_id' => $vital->vital_id,
        'handled_by' => $doctor->user_id, 'diagnosis' => 'Seasonal allergies',
        'consultation_notes' => 'Persistent sneezing', 'treatment' => 'Rest and hydration',
        'prescription' => 'Cetirizine 10 mg', 'follow_up_date' => '2026-10-21',
        'consultation_date' => $date, 'created_at' => now(),
    ]);
}

it('browses saved records by full patient name exact ID and inclusive date range', function () {
    $doctor = User::factory()->create(['role' => 'Doctor', 'name' => 'Doctor Reyes']);
    $patient = PatientInformation::factory()->create(['first_name' => 'BrowseMaria', 'middle_name' => 'Elena', 'last_name' => 'BrowseSantos']);
    $first = savedRecordForBrowsing($doctor, $patient, '2026-10-10');
    $second = savedRecordForBrowsing($doctor, $patient, '2026-10-14');
    $other = savedRecordForBrowsing($doctor, null, '2026-10-15');
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsTable::class)
        ->assertSee('Patient Name')->assertSee('Patient ID')->assertSee('Total Medical Records')->assertSee('Latest Consultation Date')
        ->assertSee('View Details')->assertDontSee('wire:submit="saveMedicalRecord"', false)
        ->assertDontSee('wire:poll.5s', false)
        ->set('search', 'BrowseMaria BrowseSantos');
    expect($component->get('patients')->pluck('patient_id')->all())->toBe([$patient->patient_id]);
    $component->set('search', 'BrowseSantos Elena BrowseMaria');
    expect($component->get('patients')->total())->toBe(1);
    $component->set('search', (string) $patient->patient_id);
    expect($component->get('patients')->total())->toBe(1);
    $component->set('fromDate', '2026-10-10')->set('toDate', '2026-10-10');
    expect($component->get('patients')->pluck('patient_id')->all())->toBe([$patient->patient_id]);
    $component->call('clearFilters')->assertSet('search', '')->assertSet('fromDate', '')->assertSet('toDate', '');
    expect($component->get('patients')->total())->toBe(2);
    Livewire::actingAs($doctor)->test(MedicalRecordsTable::class, ['patient_id' => $patient->patient_id])
        ->assertSet('search', (string) $patient->patient_id)->assertDontSee($other->patient->first_name);
});

it('paginates records and resets pagination when filters change', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    foreach (range(1, 12) as $number) {
        savedRecordForBrowsing($doctor);
    }
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsTable::class);
    expect($component->get('medicalRecords')->count())->toBe(10);
    $component->call('setPage', 2);
    expect($component->get('medicalRecords')->currentPage())->toBe(2)->and($component->get('medicalRecords')->count())->toBe(2);
    $component->set('search', 'no-matching-patient')->assertSee('No medical records match your filters.');
    expect($component->get('medicalRecords')->currentPage())->toBe(1);
    $component->call('clearFilters')->call('setPage', 2)->set('fromDate', '2026-10-14');
    expect($component->get('medicalRecords')->currentPage())->toBe(1);
    $component->call('setPage', 2)->set('toDate', '2026-10-14');
    expect($component->get('medicalRecords')->currentPage())->toBe(1);
});

it('rejects invalid date filters and recovers without returning unrelated records', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    savedRecordForBrowsing($doctor);
    $component = Livewire::actingAs($doctor)->test(MedicalRecordsTable::class)
        ->set('fromDate', 'invalid')->assertHasErrors('fromDate')->assertSee('The from date field must match the format Y-m-d.');
    expect($component->get('medicalRecords')->total())->toBe(0);
    $component->set('fromDate', '2026-10-15')->set('toDate', '2026-10-10')->assertHasErrors('toDate');
    expect($component->get('medicalRecords')->total())->toBe(0);
    $component->call('clearFilters')->assertHasNoErrors();
    expect($component->get('medicalRecords')->total())->toBe(1);
    $component->set('search', str_repeat('a', 256))->assertHasErrors('search');
    expect($component->get('medicalRecords')->total())->toBe(0);
});

it('opens each patient with complete consultation history and linked visit vital signs', function () {
    $doctor = User::factory()->create(['role' => 'Doctor', 'name' => 'Doctor Visit']);
    $staff = User::factory()->create(['name' => 'Recording Nurse']);
    $patient = PatientInformation::factory()->create(['middle_name' => 'RecordedMiddle', 'emergency_contact' => '09189998888']);
    $record = savedRecordForBrowsing($doctor, $patient, '2026-10-10');
    $record->vitalSign->update([
        'recorded_by' => $staff->user_id, 'recorded_at' => '2026-10-10 01:15:00',
        'height' => 165, 'weight' => 60, 'bmi' => 22,
    ]);
    $laterVisit = savedRecordForBrowsing($doctor, $patient, '2026-10-14');
    $laterVisit->vitalSign->update(['blood_pressure' => '150/95']);
    $laterVisit->update(['diagnosis' => 'Later visit diagnosis']);
    $other = savedRecordForBrowsing($doctor);
    $other->update(['diagnosis' => 'Other patient diagnosis']);

    $listing = Livewire::actingAs($doctor)->test(MedicalRecordsTable::class)->assertDontSee('role="dialog"', false);
    $listing->assertSeeHtml('href="'.e(route('medical-records.show', [
        'record' => $patient->patient_id, 'search' => '', 'fromDate' => '', 'toDate' => '', 'page' => 1,
    ])).'"');

    $response = $this->get(route('medical-records.show', ['record' => $patient->patient_id]))
        ->assertOk()->assertSeeLivewire(MedicalRecordDetails::class)
        ->assertSeeInOrder(['Demographic overview', 'Patient Information', 'Relevant history', 'Latest vital signs', 'Medical history', 'Prescription:'])
        ->assertSee('View All Details')->assertSee('Doctor\'s Consultation')->assertSee('Vital Signs')
        ->assertSee('RecordedMiddle')->assertSee('09189998888')->assertSee($patient->address)
        ->assertSee($patient->birthdate)->assertSee($patient->registration_date)->assertSee($patient->status)
        ->assertSee('120/80 mmHg')->assertSee('36.5')->assertSee('72 bpm')->assertSee('16 breaths/min')
        ->assertSee('165')->assertSee('60')->assertSee('22')->assertSee('Oct 10, 2026')->assertSee('9:15 AM')
        ->assertSee('Recording Nurse')->assertSee('Doctor Visit')->assertSee('Persistent sneezing')
        ->assertSee('Seasonal allergies')->assertSee('Rest and hydration')->assertSee('Cetirizine 10 mg')->assertSee('Oct 21, 2026')
        ->assertSee('150/95')->assertSee('Later visit diagnosis')
        ->assertDontSee('Other patient diagnosis')
        ->assertDontSee('wire:model', false)->assertDontSee('wire:submit', false);
    expect(substr_count($response->getContent(), 'View All Details'))->toBe(2)
        ->and(substr_count($response->getContent(), 'x-on:open-consultation-modal.window'))->toBe(2)
        ->and($response->getContent())->toContain('consultation-modal-title-'.$record->medical_record_id)
        ->and($response->getContent())->toContain('consultation-modal-title-'.$laterVisit->medical_record_id);
});

it('displays consistent placeholders for optional patient vital sign and consultation values', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $patient = PatientInformation::factory()->create(['middle_name' => null, 'contact_number' => null, 'emergency_contact' => null]);
    $record = savedRecordForBrowsing($doctor, $patient);
    $record->update(['prescription' => null, 'follow_up_date' => null, 'treatment' => ' ', 'consultation_notes' => null]);
    $record->vitalSign->update(['blood_pressure' => null, 'recorded_at' => null, 'temperature' => null, 'pulse_rate' => 0]);
    $response = $this->actingAs($doctor)->get(route('medical-records.show', ['record' => $record->patient_id]))
        ->assertOk()->assertSee('Not provided.')->assertSee('0 bpm')
        ->assertDontSee('Not available')->assertDontSee('Not prescribed')->assertDontSee('Not scheduled');
    expect(substr_count($response->getContent(), 'Not provided.'))->toBeGreaterThanOrEqual(12);
});

it('preserves search date filters and pagination when returning from a record', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $patient = PatientInformation::factory()->create();
    savedRecordForBrowsing($doctor, $patient);
    $filters = ['search' => (string) $patient->patient_id, 'fromDate' => '2026-10-01', 'toDate' => '2026-10-31', 'page' => 1];
    $listing = Livewire::actingAs($doctor)->withQueryParams($filters)->test(MedicalRecordsTable::class);
    $records = $listing->get('medicalRecords');
    expect($records->currentPage())->toBe(1)->and($records->count())->toBe(1);
    $patientListing = Livewire::actingAs($doctor)->withQueryParams($filters)->test(MedicalRecordsTable::class);
    $url = route('medical-records.show', ['record' => $patient->patient_id, ...$filters]);
    $patientListing->assertSeeHtml('href="'.e($url).'"');
    $this->actingAs($doctor)->get($url)->assertOk()
        ->assertSeeHtml('href="'.e(route('medical-records.index', $filters)).'"')->assertSee('Back to Medical Records');
    $this->get(route('medical-records.index', $filters))->assertOk();
    $restored = Livewire::withQueryParams($filters)->test(MedicalRecordsTable::class);
    expect($restored->get('medicalRecords')->modelKeys())->toBe($records->modelKeys());
    $restored->assertSet('search', $filters['search'])->assertSet('fromDate', $filters['fromDate'])->assertSet('toDate', $filters['toDate']);
});

it('authorizes direct details access for existing browsing roles', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $record = savedRecordForBrowsing($user);
    $this->actingAs($user)->get(route('medical-records.show', ['record' => $record->patient_id]))->assertOk();
})->with(['Admin', 'Doctor', 'Local Health Unit Staff']);

it('rejects unauthorized details access and returns not found for missing records', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $record = savedRecordForBrowsing($doctor);
    $this->get(route('medical-records.show', ['record' => $record->patient_id]))->assertRedirect(route('login'));
    $unauthorized = User::factory()->create();
    $unauthorized->role = 'Unassigned';
    $this->actingAs($unauthorized)->get(route('medical-records.show', ['record' => $record->patient_id]))->assertForbidden();
    Livewire::actingAs($unauthorized)->test(MedicalRecordDetails::class, ['record' => $record->patient])->assertForbidden();
    $this->actingAs($doctor)->get(route('medical-records.show', ['record' => 999999]))->assertNotFound();
    $patient = $record->patient;
    $details = Livewire::actingAs($doctor)->test(MedicalRecordDetails::class, ['record' => $patient]);
    $record->delete();
    $patient->delete();
    $this->get(route('medical-records.show', ['record' => $patient->patient_id]))->assertNotFound();
    expect(fn () => $details->call('$refresh'))->toThrow(ModelNotFoundException::class);
});
it('separates browsing and consultation routes with correct authorization and active sidebar links', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $response = $this->actingAs($user)->get(route('medical-records.index'))->assertOk()
        ->assertSeeLivewire(MedicalRecordsTable::class)->assertDontSee('wire:submit="saveMedicalRecord"', false);
    preg_match('/<nav[^>]*aria-label="Main navigation"[^>]*>(.*?)<\/nav>/s', $response->getContent(), $matches);
    expect($matches[1])->toContain('Medical Records')->and(substr_count($matches[1], 'aria-current="page"'))->toBe(1);

    if ($role === 'Local Health Unit Staff') {
        expect($matches[1])->not->toContain('Doctor&#039;s Consultation');
        $this->get(route('doctors-consultation.index'))->assertForbidden();

        return;
    }

    expect($matches[1])->toContain('Doctor&#039;s Consultation');
    $response = $this->get(route('doctors-consultation.index'))->assertOk()->assertSeeLivewire(MedicalRecordsForm::class)
        ->assertSee('Doctor&#039;s Consultation', false)->assertSee('Doctor&#039;s Consultation Queue', false);
    preg_match('/<nav[^>]*aria-label="Main navigation"[^>]*>(.*?)<\/nav>/s', $response->getContent(), $matches);
    expect(substr_count($matches[1], 'aria-current="page"'))->toBe(1);
    preg_match('/<a[^>]*href="[^"]*doctors-consultation[^"]*"[^>]*>/', $matches[1], $activeLink);
    expect($activeLink[0])->toContain('aria-current="page"');
})->with(['Admin', 'Doctor', 'Local Health Unit Staff']);

it('requires login and authorized access to browsing and record details', function () {
    $this->get(route('medical-records.index'))->assertRedirect(route('login'));
    $this->get(route('doctors-consultation.index'))->assertRedirect(route('login'));
    $user = User::factory()->create();
    $user->role = 'Unassigned';
    $this->actingAs($user)->get(route('medical-records.index'))->assertForbidden();
    Livewire::actingAs($user)->test(MedicalRecordsTable::class)->assertForbidden();
});

it('completes consultation on Doctor&#039;s Consultation and reviews the saved visit on Medical Records', function (string $role) {
    $doctor = User::factory()->create(['role' => $role]);
    $staff = User::factory()->create();
    $queue = Queue::factory()->create(['status' => 'Ready for Consultation', 'queue_number' => 42]);
    $vital = VitalSign::create([
        'queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id,
        'recorded_by' => $staff->user_id, 'blood_pressure' => '120/80', 'recorded_at' => now(),
    ]);
    Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)
        ->assertSee('Doctor&#039;s Consultation', false)->call('startConsultation', $queue->queue_id)
        ->assertSee('Patient Details')->assertSee('Current Visit Vital Signs')->assertSee('Previous Medical Records')->assertSee('Doctor&#039;s Consultation Form', false)
        ->assertSet('vital_signs_id', $vital->vital_id)
        ->set('consultation_notes', 'Persistent sneezing')->set('diagnosis', 'Seasonal allergies')->set('treatment', 'Rest and hydration')
        ->call('saveMedicalRecord')->assertHasNoErrors()->assertDispatched('medical-record-saved')
        ->assertSet('queueId', null)->assertSet('patient_id', null);
    $record = MedicalRecord::firstOrFail();
    expect($queue->fresh()->status)->toBe('Completed')->and($queue->fresh()->queue_number)->toBe(42)
        ->and($record->queue_id)->toBe($queue->queue_id)->and($record->handled_by)->toBe($doctor->user_id)
        ->and($record->vital_signs_id)->toBe($vital->vital_id)->and($record->prescription)->toBeNull()->and($record->follow_up_date)->toBeNull();
    Livewire::actingAs($doctor)->test(MedicalRecordsTable::class)
        ->set('search', (string) $queue->patient_id)->assertSee($queue->patient->first_name)
        ->assertSee('View Details');
    $this->actingAs($doctor)->get(route('medical-records.show', ['record' => $record->patient_id]))
        ->assertOk()->assertSee('Persistent sneezing')->assertSee('Seasonal allergies')->assertSee('Rest and hydration')->assertSee('120/80')->assertSee($doctor->name);
})->with(['Doctor', 'Admin']);

it('includes standalone measurements in latest vitals and exposes recorded follow-ups without changing visits', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $record = savedRecordForBrowsing($doctor);
    $measurement = $record->patient->vitalSigns()->create([
        'recorded_by' => $doctor->user_id,
        'blood_pressure' => '130/85',
        'pulse_rate' => 0,
        'recorded_at' => now()->addHour(),
    ]);

    $this->actingAs($doctor)->get(route('medical-records.show', ['record' => $record->patient_id]))
        ->assertOk()->assertSee('Latest vital signs')->assertSee('130/85 mmHg')->assertSee('0 bpm')
        ->assertSee('Recorded follow-ups')->assertSee('21 Oct 2026')
        ->assertSee('id="record-visits-tab"', false)->assertSee('id="record-vitals-tab"', false)
        ->assertSee('id="record-followups-tab"', false)->assertSee('View original entry')
        ->assertSee('Addendum and correction workflows are not available yet.')
        ->assertDontSee('wire:model', false)->assertDontSee('wire:submit', false);

    expect(MedicalRecord::count())->toBe(1)
        ->and($record->fresh()->diagnosis)->toBe('Seasonal allergies')
        ->and($measurement->fresh()->queue_id)->toBeNull();
});
