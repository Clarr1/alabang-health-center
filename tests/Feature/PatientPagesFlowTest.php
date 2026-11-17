<?php

use App\Livewire\PatientInformationForm;
use App\Livewire\VitalSignsForm;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Services\QueueWorkflow;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('saves patient information on one page and vital signs on the next page', function () {
    $user = User::factory()->create();

    $form = Livewire::actingAs($user)
        ->test(PatientInformationForm::class)
        ->set('first_name', 'Anna')
        ->set('middle_name', 'Marie')
        ->set('last_name', 'Dela Cruz')
        ->set('birthdate', '1995-02-14')
        ->set('age', 31)
        ->set('sex', 'Female')
        ->set('address', 'Sample Street 101')
        ->set('contact_number', '09170001111')
        ->set('emergency_contact', 'Jose Dela Cruz')
        ->set('registration_date', '2026-09-23')
        ->call('savePatient')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertDispatched('patient-information-saved', fn (string $event, array $parameters): bool => $parameters === [])
        ->assertSet('patient', null)
        ->assertSet('first_name', '')
        ->assertSet('middle_name', '')
        ->assertSet('last_name', '')
        ->assertSet('birthdate', '')
        ->assertSet('age', '')
        ->assertSet('sex', 'Male')
        ->assertSet('address', '')
        ->assertSet('contact_number', '')
        ->assertSet('emergency_contact', '')
        ->assertSet('registration_date', now('Asia/Manila')->toDateString())
        ->assertSee('Patient registered successfully.')
        ->assertDontSee('wire:model="status"', false)
        ->assertDontSee('Livewire.navigate', false);

    $patient = PatientInformation::sole();
    expect($patient->first_name)->toBe('Anna')
        ->and($patient->middle_name)->toBe('Marie')
        ->and($patient->last_name)->toBe('Dela Cruz')
        ->and($patient->sex)->toBe('Female')
        ->and($patient->address)->toBe('Sample Street 101')
        ->and($patient->status)->toBe('Active');
    $form->assertSet('registeredPatientId', $patient->patient_id)
        ->assertSee('Printable healthcare ID')
        ->assertSee(route('patients.card.show', $patient))
        ->assertSee(route('patients.card.download', $patient))
        ->assertSee(route('medical-records.show', ['record' => $patient->patient_id]))
        ->assertSee('No queue number has been assigned.')
        ->call('savePatient')->assertHasNoErrors();
    expect(PatientInformation::count())->toBe(1);
    expect(Queue::count())->toBe(0);
    $queue = app(QueueWorkflow::class)->addPatient($patient->patient_id, 19);
    app(QueueWorkflow::class)->transition($queue->queue_id, 'Waiting', 'Called');
    app(QueueWorkflow::class)->transition($queue->queue_id, 'Called', 'Vital Signs');

    Livewire::actingAs($user)
        ->test(VitalSignsForm::class, ['patient_id' => $patient->patient_id])
        ->set('blood_pressure', '120/80')
        ->set('temperature', 36.5)
        ->set('pulse_rate', 72)
        ->set('respiratory_rate', 18)
        ->set('height', 160)
        ->set('weight', 55)
        ->call('saveVitalSigns')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('vital_signs', [
        'patient_id' => $patient->patient_id,
        'blood_pressure' => '120/80',
        'recorded_by' => $user->user_id,
    ]);
});

it('searches for a patient and associates vital signs with the selected patient', function () {
    $user = User::factory()->create();
    $patient = PatientInformation::create([
        'first_name' => 'Maria',
        'middle_name' => 'Elena',
        'last_name' => 'Santos',
        'birthdate' => '1998-05-10',
        'age' => 28,
        'sex' => 'Female',
        'address' => 'Sample Address 123',
        'contact_number' => '09171234567',
        'registration_date' => '2026-09-23',
        'status' => 'Active',
    ]);

    Livewire::actingAs($user)
        ->test(VitalSignsForm::class)
        ->set('patientSearch', 'Santos')
        ->assertSee('Maria')
        ->call('selectPatient', $patient->patient_id)
        ->assertSet('patient_id', $patient->patient_id)
        ->set('blood_pressure', '120/80')
        ->call('saveVitalSigns')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('vital_signs', [
        'patient_id' => $patient->patient_id,
        'blood_pressure' => '120/80',
        'recorded_by' => $user->user_id,
    ]);
});

it('preserves patient details when registration validation fails', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(PatientInformationForm::class)
        ->set('first_name', 'Anna')
        ->set('sex', 'Female')
        ->set('contact_number', '09170001111')
        ->call('savePatient')
        ->assertHasErrors(['last_name', 'birthdate', 'age', 'address'])
        ->assertNoRedirect()
        ->assertNotDispatched('patient-information-saved')
        ->assertSet('first_name', 'Anna')
        ->assertSet('sex', 'Female')
        ->assertSet('contact_number', '09170001111');

    expect(PatientInformation::count())->toBe(0);
});

it('calculates patient age from birthdate using the Manila day', function (string $birthdate, int|string $expectedAge) {
    $this->travelTo(new DateTimeImmutable('2026-10-28 16:15:00 UTC'));

    Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->set('birthdate', $birthdate)->assertSet('age', $expectedAge);
})->with([
    'birthday today in Manila' => ['2000-10-29', 26],
    'birthday tomorrow' => ['2000-10-30', 25],
    'newborn' => ['2026-10-29', 0],
    'invalid date' => ['not-a-date', ''],
    'future date' => ['2026-10-30', ''],
]);

it('validates and reviews registration details without creating a patient until submitted', function () {
    $form = Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->call('reviewDetails')->assertHasErrors(['first_name', 'last_name', 'birthdate', 'age', 'address'])
        ->assertSet('showReview', false)
        ->set('first_name', 'ReviewAnna')->set('last_name', 'ReviewSantos')->set('birthdate', '1995-02-14')
        ->set('address', 'Alabang, Muntinlupa City')->set('emergency_contact', 'Jose, spouse, 09170001111')
        ->set('contact_number', '09170001111')
        ->call('reviewDetails')->assertHasNoErrors()->assertSet('showReview', true)
        ->assertSee('Review patient details')->assertSee('ReviewAnna')->assertSee('Jose, spouse, 09170001111');

    expect(PatientInformation::count())->toBe(0);
    $form->call('returnToDetails')->assertSet('showReview', false)->assertSet('first_name', 'ReviewAnna')
        ->call('reviewDetails')->set('address', 'Updated Alabang address')->assertSet('showReview', false)
        ->call('reviewDetails')->call('savePatient')->assertHasNoErrors()->assertSet('showReview', false);
    expect(PatientInformation::count())->toBe(1)->and(Queue::count())->toBe(0);
});

it('shows advisory existing-patient matches by first name last name and birthdate', function () {
    $existing = PatientInformation::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'birthdate' => '1992-05-14']);
    PatientInformation::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'birthdate' => '1993-05-14']);
    $form = Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->set('first_name', ' maria ')->set('last_name', 'SANTOS')->set('birthdate', '1992-05-14')
        ->assertSee('Possible existing patient records');

    expect($form->get('duplicatePatients')->modelKeys())->toBe([$existing->patient_id]);
    $form->set('birthdate', '1994-05-14')->assertSee('Duplicate check: no matching record found');
    expect($form->get('duplicatePatients'))->toHaveCount(0);
    expect(PatientInformation::count())->toBe(2);
});

it('starts another registration only after explicitly leaving the generated ID screen', function () {
    $form = Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class)
        ->set('first_name', 'First')->set('last_name', 'Patient')->set('birthdate', '1995-02-14')
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set('address', 'Alabang')->call('savePatient')->assertHasNoErrors();
    $firstPatientId = PatientInformation::sole()->patient_id;
    $form->assertSet('registeredPatientId', $firstPatientId)
        ->assertDontSee('wire:submit.prevent="savePatient"', false)
        ->call('resetForm')->assertSet('registeredPatientId', null)
        ->assertSee('Patient Registration')->assertDontSee('Printable healthcare ID')
        ->assertSet('first_name', '')->assertSet('showReview', false)
        ->call('savePatient')->assertHasErrors(['first_name', 'last_name', 'birthdate', 'age', 'address'])
        ->set('first_name', 'Second')->set('last_name', 'Patient')->set('birthdate', '2000-01-01')
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set('address', 'Alabang')->call('savePatient')->assertHasNoErrors();
    expect(PatientInformation::count())->toBe(2)->and(Queue::count())->toBe(0);
    expect($form->get('registeredPatientId'))->not->toBe($firstPatientId);
});

it('prevents changing the registered patient ID from the client', function () {
    $form = Livewire::actingAs(User::factory()->create())->test(PatientInformationForm::class);
    expect(fn () => $form->set('registeredPatientId', 999))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('does not expose a generated ID or save a patient when registration is unauthorized', function () {
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))
        ->test(PatientInformationForm::class)->call('savePatient')->assertForbidden();
    expect(PatientInformation::count())->toBe(0);
});
