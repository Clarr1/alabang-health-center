<?php

use App\Livewire\PatientVitalSignForm;
use App\Models\PatientInformation;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

it('stores patient information and vital signs from the livewire form', function () {
    $user = User::factory()->create();

    $form = Livewire::actingAs($user)
        ->test(PatientVitalSignForm::class)
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
        ->assertHasNoErrors()->assertSet('first_name', '')->assertSet('blood_pressure', '')
        ->assertSee('Register another patient')->call('save')->assertHasNoErrors();

    $patient = PatientInformation::where('first_name', 'Maria')->first();
    expect($patient)->not->toBeNull()
        ->and($patient->first_name)->toBe('Maria')
        ->and(PatientInformation::count())->toBe(1)
        ->and(VitalSign::where('patient_id', $patient->patient_id)->count())->toBe(1)
        ->and(VitalSign::where('patient_id', $patient->patient_id)->first()->recorded_by)->toBe($user->user_id);

    $form->call('resetForm')->assertSet('registeredPatientId', null)->assertSee('Save Patient & Vital Signs', false);
});

it('retains legacy form inputs and creates neither record on invalid vitals', function () {
    Livewire::actingAs(User::factory()->create())->test(PatientVitalSignForm::class)
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set('first_name', 'Synthetic')->set('last_name', 'Patient')->set('birthdate', '2000-01-01')
        ->set('address', 'Synthetic address')->set('blood_pressure', '120/')
        ->call('save')->assertHasErrors('blood_pressure')->assertSet('first_name', 'Synthetic');
    expect(PatientInformation::count())->toBe(0)->and(VitalSign::count())->toBe(0);
});

it('rolls back the legacy patient registration if the vital sign insert fails', function () {
    $form = Livewire::actingAs(User::factory()->create())->test(PatientVitalSignForm::class)
        ->set('contact_number', '09171234567')->set('emergency_contact', 'Jose Santos, relative, 09171234568')
        ->set('first_name', 'Synthetic')->set('last_name', 'Patient')->set('birthdate', '2000-01-01')
        ->set('address', 'Synthetic address')->set('blood_pressure', '120/80');
    VitalSign::creating(function (): void {
        throw new QueryException('sqlite', 'insert', [], new RuntimeException('Simulated vital sign failure'));
    });

    try {
        $form->call('savePatient')->assertHasErrors('patient')->assertSet('first_name', 'Synthetic')
            ->assertSet('registeredPatientId', null)->assertNotDispatched('patient-information-saved');
        expect(PatientInformation::count())->toBe(0)->and(VitalSign::count())->toBe(0);
    } finally {
        VitalSign::flushEventListeners();
    }
    $form->call('save')->assertHasNoErrors();
    expect(PatientInformation::count())->toBe(1)->and(VitalSign::count())->toBe(1);
});

it('rejects doctors on the legacy combined registration component', function () {
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))
        ->test(PatientVitalSignForm::class)->assertForbidden();
    expect(PatientInformation::count())->toBe(0)->and(VitalSign::count())->toBe(0);
});
