<?php

use App\Livewire\PatientTable;
use App\Models\Household;
use App\Models\PatientInformation;
use App\Models\User;
use App\Services\PatientCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;
use Livewire\Livewire;
use SimpleSoftwareIO\QrCode\Generator;

function createCardPatient(array $attributes = []): PatientInformation
{
    return PatientInformation::factory()->create(array_merge([
        'household_id' => Household::factory(),
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
        ->assertSee('<div class="family-number">'.$patient->household->family_number.'</div>', false)
        ->assertDontSee('FN#')
        ->assertDontSee('Patient ID')
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

it('keeps individual patient QR codes distinct for patients sharing a family number', function () {
    $household = Household::factory()->create();
    $first = createCardPatient(['household_id' => $household->id]);
    $second = createCardPatient(['household_id' => $household->id]);
    $data = app(PatientCard::class)->data($first);
    $expected = (new Generator)->format('svg')->size(240)->margin(4)->errorCorrection('M')
        ->generate((string) $first->patient_id);

    expect($data['familyNumber'])->toBe($household->family_number)
        ->and(app(PatientCard::class)->data($second)['familyNumber'])->toBe($household->family_number)
        ->and($data['qrCode'])->toBe('data:image/svg+xml;base64,'.base64_encode((string) $expected))
        ->not->toBe(app(PatientCard::class)->data($second)['qrCode']);
});

it('downloads a single page PDF with standard ID card dimensions', function () {
    $patient = createCardPatient();
    $renderedFamilyNumber = null;
    View::composer('patient-cards.document', function (ViewInstance $view) use (&$renderedFamilyNumber): void {
        $renderedFamilyNumber = $view->getData()['familyNumber'];
    });
    $response = $this->actingAs(User::factory()->create())
        ->get(route('patients.card.download', $patient))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=patient-card-'.$patient->patient_id.'.pdf');

    $pdf = $response->getContent();
    expect($renderedFamilyNumber)->toBe($patient->household->family_number);
    expect(str_starts_with($pdf, '%PDF-'))->toBeTrue();
    expect(str_contains($pdf, '/Count 1'))->toBeTrue();
    expect(str_contains($pdf, '/MediaBox [0.000 0.000 242.646 153.014]'))->toBeTrue();
});

it('shows the family number from the correct associated household without modifying identifiers', function () {
    $household = Household::factory()->create(['family_number' => 'FN-12345678']);
    $otherHousehold = Household::factory()->create(['family_number' => 'FN-87654321']);
    $patient = createCardPatient(['household_id' => $household->id]);
    $attributes = $patient->fresh()->getAttributes();

    $this->actingAs(User::factory()->create())->get(route('patients.card.show', $patient))
        ->assertOk()->assertSee('<div class="family-number">FN-12345678</div>', false)->assertDontSee($otherHousehold->family_number)
        ->assertDontSee('Patient ID');
    expect($patient->fresh()->getAttributes())->toEqual($attributes)
        ->and(Household::count())->toBe(2);
});

it('uses the current family association when a patient transfers households', function () {
    $patient = createCardPatient();
    $previousHousehold = $patient->household;
    $household = Household::factory()->create();
    $patient->update(['household_id' => $household->id]);

    expect(app(PatientCard::class)->data($patient)['familyNumber'])->toBe($household->family_number);
    $this->actingAs(User::factory()->create())->get(route('patients.card.show', $patient))
        ->assertOk()->assertSee('<div class="family-number">'.$household->family_number.'</div>', false)
        ->assertDontSee($previousHousehold->family_number);
});

it('shows a clear message without creating a family or falling back to a patient ID', function () {
    $patient = createCardPatient(['household_id' => null]);
    $attributes = $patient->fresh()->getAttributes();
    $this->actingAs(User::factory()->create())->get(route('patients.card.show', $patient))
        ->assertOk()->assertSee('<div class="family-number">Not assigned</div>', false)
        ->assertSee('Complete the patient’s family association to assign a family number.')
        ->assertDontSee('Patient ID');
    expect(app(PatientCard::class)->data($patient)['familyNumber'])->toBeNull()
        ->and($patient->fresh()->getAttributes())->toEqual($attributes)
        ->and(Household::count())->toBe(0);
    $this->get(route('patients.card.download', $patient))->assertOk()->assertHeader('Content-Type', 'application/pdf');
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
