<?php

use App\Livewire\PatientInformationForm;
use App\Livewire\VitalSignsForm;
use App\Models\PatientInformation;
use App\Models\User;
use Livewire\Livewire;

it('saves patient information on one page and vital signs on the next page', function () {
    $user = User::factory()->create();

    $patient = Livewire::actingAs($user)
        ->test(PatientInformationForm::class)
        ->set('qr_code', 'QR-9001')
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
        ->set('status', 'Active')
        ->call('savePatient')
        ->assertHasNoErrors()
        ->instance()->patient;

    expect($patient)->not->toBeNull()
        ->and($patient->qr_code)->toBe('QR-9001');

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
        'qr_code' => 'QR-SEARCH-01',
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
