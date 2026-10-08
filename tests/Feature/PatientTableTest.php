<?php

use App\Livewire\PatientTable;
use App\Models\PatientInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createTablePatient(array $attributes = []): PatientInformation
{
    return PatientInformation::create(array_merge([
        'qr_code' => 'QR-'.uniqid(),
        'first_name' => 'Maria',
        'middle_name' => 'Elena',
        'last_name' => 'Santos',
        'birthdate' => '1998-05-10',
        'age' => 28,
        'sex' => 'Female',
        'address' => 'Sample Address 123',
        'contact_number' => '09171234567',
        'emergency_contact' => 'Jose Santos',
        'registration_date' => '2026-09-23',
        'status' => 'Active',
    ], $attributes));
}

it('searches, paginates, and views a selected patient', function () {
    $user = User::factory()->create();
    $patient = createTablePatient();

    Livewire::actingAs($user)
        ->test(PatientTable::class)
        ->assertSee('Santos')
        ->set('page', 2)
        ->set('search', 'Maria')
        ->assertSet('page', 1)
        ->call('viewPatient', $patient->patient_id)
        ->assertSet('selectedPatientId', $patient->patient_id)
        ->assertSet('showPatientModal', true)
        ->assertSee('Sample Address 123')
        ->call('closePatientModal')
        ->assertSet('showPatientModal', false);
});

it('shows an empty state when no patients match the search', function () {
    $user = User::factory()->create();
    createTablePatient();

    Livewire::actingAs($user)
        ->test(PatientTable::class)
        ->set('search', 'does-not-exist')
        ->assertSee('No patients found.');
});