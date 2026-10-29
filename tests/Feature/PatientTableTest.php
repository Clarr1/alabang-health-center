<?php

use App\Livewire\PatientInformationForm;
use App\Livewire\PatientTable;
use App\Models\PatientInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createTablePatient(array $attributes = []): PatientInformation
{
    return PatientInformation::create(array_merge([
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

it('links the patients page to the patient registration form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('patients.index'))
        ->assertOk()
        ->assertSee('href="'.route('patient.information-form').'" wire:navigate', false)
        ->assertSee('Register patient');

    $this->get(route('patient.information-form'))
        ->assertOk()
        ->assertSeeLivewire(PatientInformationForm::class);
});

it('searches, paginates, and views a selected patient', function () {
    $user = User::factory()->create();
    $patient = createTablePatient();

    Livewire::actingAs($user)
        ->test(PatientTable::class)
        ->assertSee('Santos')
        ->call('setPage', 2)
        ->set('search', 'Maria')
        ->assertSet('paginators.page', 1)
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

it('combines database record status and sex filters and resets pagination', function () {
    $user = User::factory()->create();
    PatientInformation::factory()->create(['first_name' => 'Matching', 'status' => 'Inactive', 'sex' => 'Female']);
    PatientInformation::factory()->create(['first_name' => 'OtherStatus', 'status' => 'Active', 'sex' => 'Female']);
    PatientInformation::factory()->create(['first_name' => 'OtherSex', 'status' => 'Inactive', 'sex' => 'Male']);

    Livewire::actingAs($user)->test(PatientTable::class)
        ->assertSee('1 active patient records')
        ->call('setPage', 2)
        ->set('recordStatus', 'Inactive')
        ->assertSet('paginators.page', 1)
        ->set('sex', 'Female')
        ->assertSee('Matching')
        ->assertDontSee('OtherStatus')
        ->assertDontSee('OtherSex')
        ->set('search', 'absent')
        ->assertSee('No patients found.')
        ->call('resetFilters')
        ->assertSet('recordStatus', '')
        ->assertSet('sex', '')
        ->assertSet('search', '')
        ->assertSee('OtherStatus');
});

it('sorts by the latest recorded visit and filters visit dates in Manila', function () {
    $this->travelTo(Carbon::parse('2026-10-28 00:30:00', 'Asia/Manila'));
    $user = User::factory()->create();
    $recent = PatientInformation::factory()->create(['first_name' => 'RecentPatient']);
    $older = PatientInformation::factory()->create(['first_name' => 'OlderPatient']);
    $unvisited = PatientInformation::factory()->create(['first_name' => 'UnvisitedPatient']);
    $vitalSign = $older->vitalSigns()->create(['recorded_by' => $user->user_id, 'recorded_at' => '2026-09-01 09:00:00']);
    $older->medicalRecords()->create([
        'vital_signs_id' => $vitalSign->vital_id,
        'handled_by' => $user->user_id,
        'diagnosis' => 'Routine visit',
        'consultation_date' => '2026-10-22',
        'created_at' => '2026-10-22 10:00:00',
    ]);
    $recent->vitalSigns()->create(['recorded_by' => $user->user_id, 'recorded_at' => '2026-10-28 00:15:00']);

    Livewire::actingAs($user)->test(PatientTable::class)
        ->assertSeeInOrder(['RecentPatient', 'OlderPatient', 'UnvisitedPatient'])
        ->assertSee('28 Oct 2026')
        ->assertSee('22 Oct 2026')
        ->set('lastVisit', 'today')
        ->assertSee('RecentPatient')->assertDontSee('OlderPatient')->assertDontSee('UnvisitedPatient')
        ->set('lastVisit', 'week')
        ->assertSee('RecentPatient')->assertSee('OlderPatient')->assertDontSee('UnvisitedPatient')
        ->set('lastVisit', 'never')
        ->assertSee('UnvisitedPatient')->assertDontSee('RecentPatient')->assertDontSee('OlderPatient');
});

it('paginates real patient counts and resets to the first page when sex changes', function () {
    PatientInformation::factory()->count(11)->create(['sex' => 'Female']);

    Livewire::actingAs(User::factory()->create())->test(PatientTable::class)
        ->assertSee('Showing 1–10 of 11 patients')
        ->call('nextPage')
        ->assertSee('Showing 11–11 of 11 patients')
        ->set('sex', 'Female')
        ->assertSet('paginators.page', 1)
        ->assertSee('Showing 1–10 of 11 patients');
});

it('searches full names, unique IDs, and contact numbers without bypassing filters', function () {
    $patient = PatientInformation::factory()->create([
        'first_name' => 'UniqueMaria', 'middle_name' => 'Elena', 'last_name' => 'Santos',
        'contact_number' => '09998887777', 'sex' => 'Female',
    ]);
    PatientInformation::factory()->create(['first_name' => 'UnrelatedName', 'sex' => 'Male']);

    Livewire::actingAs(User::factory()->create())->test(PatientTable::class)
        ->set('search', 'UniqueMaria Santos')->assertSee('UniqueMaria')->assertDontSee('UnrelatedName')
        ->set('search', 'UniqueMaria Elena Santos')->assertSee('UniqueMaria')
        ->set('search', (string) $patient->patient_id)->assertSee('UniqueMaria')
        ->set('search', '09998887777')->assertSee('UniqueMaria')->assertDontSee('UnrelatedName')
        ->set('sex', 'Male')->assertSee('No patients found.');
});
