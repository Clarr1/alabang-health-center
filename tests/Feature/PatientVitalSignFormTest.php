<?php

use App\Livewire\PatientVitalSignForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

it('stores patient information and vital signs from the livewire form', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(PatientVitalSignForm::class)
        ->set('qr_code', 'QR-1001')
        ->set('first_name', 'Maria')
        ->set('middle_name', 'Del')
        ->set('last_name', 'Santos')
        ->set('birthdate', '1998-05-10')
        ->set('age', 28)
        ->set('sex', 'Female')
        ->set('address', 'Sample Address 123')
        ->set('contact_number', '09171234567')
        ->set('emergency_contact', 'Juan Santos')
        ->set('registration_date', '2026-09-23')
        ->set('status', 'Active')
        ->set('blood_pressure', '120/80')
        ->set('temperature', 36.5)
        ->set('pulse_rate', 72)
        ->set('respiratory_rate', 18)
        ->set('height', 160)
        ->set('weight', 55)
        ->call('save')
        ->assertHasNoErrors();

    $patient = \App\Models\PatientInformation::where('qr_code', 'QR-1001')->first();
    expect($patient)->not->toBeNull()
        ->and($patient->first_name)->toBe('Maria')
        ->and(\App\Models\VitalSign::where('patient_id', $patient->patient_id)->count())->toBe(1)
        ->and(\App\Models\VitalSign::where('patient_id', $patient->patient_id)->first()->recorded_by)->toBe($user->user_id);
});
