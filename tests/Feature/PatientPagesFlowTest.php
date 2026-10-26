<?php

use App\Livewire\PatientInformationForm;
use App\Livewire\VitalSignsForm;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Services\QueueWorkflow;
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
    $form->call('savePatient')->assertHasErrors(['first_name', 'last_name', 'birthdate', 'age', 'address']);
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
