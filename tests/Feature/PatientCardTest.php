<?php

use App\Livewire\PatientTable;
use App\Models\PatientInformation;
use App\Models\User;
use App\Services\PatientCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use SimpleSoftwareIO\QrCode\Generator;

function createCardPatient(array $attributes = []): PatientInformation
{
    return PatientInformation::create(array_merge([
        'first_name' => 'Maria',
        'middle_name' => 'Elena',
        'last_name' => 'Santos',
        'birthdate' => '1998-05-10',
        'age' => 28,
        'sex' => 'Female',
        'address' => '123 Alabang, Muntinlupa City',
        'contact_number' => '09171234567',
        'emergency_contact' => 'Jose Santos',
        'registration_date' => '2026-09-23',
        'status' => 'Active',
    ], $attributes));
}

it('opens and closes the card modal without changing the patient record', function () {
    $patient = createCardPatient();
    $attributes = $patient->fresh()->getAttributes();

    Livewire::actingAs(User::factory()->create())
        ->test(PatientTable::class)
        ->assertSee('Generate ID Card')
        ->call('generateIdCard', $patient->patient_id)
        ->assertSet('cardPatientId', $patient->patient_id)
        ->assertSet('showCardModal', true)
        ->assertSee('Download PDF')
        ->assertSee(route('patients.card.show', $patient))
        ->call('closeCardModal')
        ->assertSet('cardPatientId', null)
        ->assertSet('showCardModal', false);

    expect($patient->fresh()->getAttributes())->toEqual($attributes);
});

it('renders the branded photo-free card with existing demographics and no medical details', function () {
    $patient = createCardPatient();
    $this->actingAs(User::factory()->create())
        ->get(route('patients.card.show', $patient))
        ->assertOk()
        ->assertSee('Alabang Health Center')
        ->assertSee('Maria Elena Santos')
        ->assertSee('May 10, 1998')
        ->assertSee($patient->address)
        ->assertSee('Barangay Alabang')
        ->assertSee('City of Muntinlupa')
        ->assertSee('Department of Health')
        ->assertSee('data:image/png;base64,', false)
        ->assertDontSee('Patient photo placeholder')
        ->assertDontSee('photo-placeholder')
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertDontSee($patient->contact_number)
        ->assertDontSee($patient->emergency_contact);
});

it('encodes only the patient ID and produces a different QR for each patient', function () {
    $first = createCardPatient();
    $second = createCardPatient();
    $data = app(PatientCard::class)->data($first);
    $expected = (new Generator)->format('svg')->size(240)->margin(4)->errorCorrection('M')
        ->generate((string) $first->patient_id);

    expect($data['qrCode'])->toBe('data:image/svg+xml;base64,'.base64_encode((string) $expected))
        ->not->toBe(app(PatientCard::class)->data($second)['qrCode']);
});

it('downloads a single page PDF with standard ID card dimensions', function () {
    $patient = createCardPatient();
    $response = $this->actingAs(User::factory()->create())
        ->get(route('patients.card.download', $patient))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=patient-card-'.$patient->patient_id.'.pdf');

    $pdf = $response->getContent();
    expect(str_starts_with($pdf, '%PDF-'))->toBeTrue();
    expect(str_contains($pdf, '/Count 1'))->toBeTrue();
    expect(str_contains($pdf, '/MediaBox [0.000 0.000 242.646 153.014]'))->toBeTrue();
});

it('requires authentication for card preview and PDF download', function (string $route) {
    $patient = createCardPatient();
    $this->get(route($route, $patient))->assertRedirect(route('login'));
})->with(['patients.card.show', 'patients.card.download']);

it('returns not found for a missing patient', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route, 999999))->assertNotFound();
})->with(['patients.card.show', 'patients.card.download']);

it('rejects card generation for a missing patient', function () {
    $component = Livewire::actingAs(User::factory()->create())->test(PatientTable::class);

    expect(fn () => $component->call('generateIdCard', 999999))
        ->toThrow(ModelNotFoundException::class);
});
