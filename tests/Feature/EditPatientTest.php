<?php

use App\Livewire\EditPatient;
use App\Livewire\PatientTable;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Models\VitalSign;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('links each patient to their own prefilled edit page', function () {
    $staff = User::factory()->create();
    $patients = PatientInformation::factory()->count(2)->create([
        'middle_name' => 'Elena',
        'emergency_contact' => 'Jose Santos',
        'status' => 'Follow-up',
    ]);

    $table = Livewire::actingAs($staff)->test(PatientTable::class);

    foreach ($patients as $patient) {
        $table->assertSee('href="'.route('patients.edit', $patient).'" wire:navigate', false);
        $this->get(route('patients.edit', $patient))
            ->assertOk()
            ->assertSeeLivewire(EditPatient::class)
            ->assertSee('Edit Patient');

        $edit = Livewire::actingAs($staff)->test(EditPatient::class, ['patient' => $patient]);
        $edit->assertSet('patientId', $patient->patient_id);

        foreach ($patient->only(array_keys($edit->instance()->rules())) as $field => $value) {
            $edit->assertSet($field, $value);
        }
    }
});

it('updates only the selected patient and displays the saved details in the patient table', function (string $status) {
    $staff = User::factory()->create();
    $patient = PatientInformation::factory()->create();
    $otherPatient = PatientInformation::factory()->create();
    $otherAttributes = $otherPatient->fresh()->getAttributes();
    $queue = Queue::factory()->create(['patient_id' => $patient->patient_id, 'status' => 'Completed']);
    $vital = VitalSign::create([
        'patient_id' => $patient->patient_id, 'queue_id' => $queue->queue_id,
        'recorded_by' => $staff->user_id, 'blood_pressure' => '120/80',
    ]);
    $record = MedicalRecord::create([
        'patient_id' => $patient->patient_id, 'queue_id' => $queue->queue_id,
        'vital_signs_id' => $vital->vital_id, 'handled_by' => $staff->user_id,
        'diagnosis' => 'Seasonal allergies', 'consultation_date' => now()->toDateString(), 'created_at' => now(),
    ]);
    $queueAttributes = $queue->fresh()->getAttributes();
    $vitalAttributes = $vital->fresh()->getAttributes();
    $recordAttributes = $record->fresh()->getAttributes();
    $details = [
        'first_name' => 'Updated Maria', 'middle_name' => 'Reyes', 'last_name' => 'Santos',
        'birthdate' => '1998-05-10', 'age' => 28, 'sex' => 'Female',
        'address' => 'Updated Alabang Address', 'contact_number' => '09170001111',
        'emergency_contact' => 'Jose Santos', 'registration_date' => '2026-09-23', 'status' => $status,
    ];

    $edit = Livewire::actingAs($staff)->test(EditPatient::class, ['patient' => $patient]);
    foreach ($details as $field => $value) {
        $edit->set($field, $value);
    }
    $edit->call('saveChanges')->assertHasNoErrors()
        ->assertDispatched('patient-updated', redirectUrl: route('patients.index'))
        ->assertSee("window.alert('Patient updated successfully.'); Livewire.navigate", false);

    $this->assertDatabaseHas('patient_information', ['patient_id' => $patient->patient_id, ...$details]);
    expect(PatientInformation::count())->toBe(2)
        ->and($otherPatient->fresh()->getAttributes())->toEqual($otherAttributes)
        ->and($patient->queues()->sole()->getAttributes())->toEqual($queueAttributes)
        ->and($patient->vitalSigns()->sole()->getAttributes())->toEqual($vitalAttributes)
        ->and($patient->medicalRecords()->sole()->getAttributes())->toEqual($recordAttributes);
    $this->get(route('patients.index'))->assertOk()->assertSee('Updated Maria')->assertSee($status);
})->with(['Active', 'Inactive', 'Follow-up']);

it('shows field errors without updating or navigating when patient details are invalid', function (string $field, mixed $value) {
    $patient = PatientInformation::factory()->create();
    $attributes = $patient->fresh()->getAttributes();

    Livewire::actingAs(User::factory()->create())
        ->test(EditPatient::class, ['patient' => $patient])
        ->set($field, $value)
        ->call('saveChanges')
        ->assertHasErrors($field)
        ->assertSee('id="patient-'.$field.'-error"', false)
        ->assertNotDispatched('patient-updated')
        ->assertNoRedirect();

    expect($patient->fresh()->getAttributes())->toEqual($attributes);
})->with([
    'missing first name' => ['first_name', ''],
    'missing last name' => ['last_name', ''],
    'invalid birthdate' => ['birthdate', 'not-a-date'],
    'negative age' => ['age', -1],
    'age too large' => ['age', 151],
    'invalid sex' => ['sex', 'Other'],
    'missing address' => ['address', ''],
    'contact number too long' => ['contact_number', str_repeat('1', 21)],
    'invalid registration date' => ['registration_date', 'not-a-date'],
    'missing status' => ['status', ''],
    'queue status used as patient status' => ['status', 'Waiting'],
]);

it('allows nullable patient details to stay empty', function () {
    $patient = PatientInformation::factory()->create([
        'middle_name' => null, 'contact_number' => null, 'emergency_contact' => null,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(EditPatient::class, ['patient' => $patient])
        ->assertSet('middle_name', null)
        ->assertSet('contact_number', null)
        ->assertSet('emergency_contact', null)
        ->call('saveChanges')->assertHasNoErrors()->assertDispatched('patient-updated');
});

it('cancels through a link to the patient page without saving pending changes', function () {
    $patient = PatientInformation::factory()->create();
    $attributes = $patient->fresh()->getAttributes();

    Livewire::actingAs(User::factory()->create())
        ->test(EditPatient::class, ['patient' => $patient])
        ->set('first_name', 'Unsaved change')->set('status', 'Inactive')
        ->assertSee('href="'.route('patients.index').'" wire:navigate', false)
        ->assertSee('Cancel');

    $this->get(route('patients.index'))->assertOk()->assertDontSee('Unsaved change');
    expect($patient->fresh()->getAttributes())->toEqual($attributes);
});

it('allows administrators and health unit staff to edit patients', function (string $role) {
    $patient = PatientInformation::factory()->create();
    Livewire::actingAs(User::factory()->create(['role' => $role]))
        ->test(EditPatient::class, ['patient' => $patient])
        ->set('status', 'Inactive')
        ->call('saveChanges')->assertHasNoErrors()->assertDispatched('patient-updated');
})->with(['Admin', 'Local Health Unit Staff']);

it('rejects guests and unauthorized users on the edit page', function () {
    $patient = PatientInformation::factory()->create();
    $this->get(route('patients.edit', $patient))->assertRedirect(route('login'));
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $this->actingAs($doctor)->get(route('patients.edit', $patient))->assertForbidden();
    Livewire::actingAs($doctor)->test(EditPatient::class, ['patient' => $patient])->assertForbidden();
    Livewire::actingAs($doctor)->test(PatientTable::class)->assertDontSee(route('patients.edit', $patient));
});

it('checks authorization again before saving patient changes', function () {
    $staff = User::factory()->create();
    $patient = PatientInformation::factory()->create();
    $edit = Livewire::actingAs($staff)->test(EditPatient::class, ['patient' => $patient]);
    $edit->set('status', 'Inactive');
    $staff->forceFill(['role' => 'Doctor'])->save();
    Livewire::actingAs($staff->fresh());
    $edit->call('saveChanges')->assertForbidden();
    expect($patient->fresh()->status)->toBe('Active');
});

it('returns not found for a missing patient', function () {
    $this->actingAs(User::factory()->create())->get(route('patients.edit', 999999))->assertNotFound();
});

it('prevents changing the selected patient identifier from the browser', function () {
    $patient = PatientInformation::factory()->create();
    $otherPatient = PatientInformation::factory()->create();
    $edit = Livewire::actingAs(User::factory()->create())->test(EditPatient::class, ['patient' => $patient]);

    expect(fn () => $edit->set('patientId', $otherPatient->patient_id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('reviews unsaved changes without writing and excludes the selected patient from duplicate matches', function () {
    $patient = PatientInformation::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'birthdate' => '1992-05-14']);
    $edit = Livewire::actingAs(User::factory()->create())->test(EditPatient::class, ['patient' => $patient])
        ->assertSee('Duplicate check: no other matching record found');
    expect($edit->get('duplicatePatients'))->toHaveCount(0);
    $other = PatientInformation::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos', 'birthdate' => '1992-05-14']);
    $edit->call('$refresh')->assertSee('Possible duplicate patient records');
    expect($edit->get('duplicatePatients')->modelKeys())->toBe([$other->patient_id]);
    $edit->set('address', 'Unsaved review address')->set('status', 'Inactive')
        ->call('reviewDetails')->assertHasNoErrors()->assertSet('showReview', true)
        ->assertSee('Unsaved review address')->assertSee('Inactive');
    expect($patient->fresh()->status)->toBe('Active')->and($patient->fresh()->address)->not->toBe('Unsaved review address');
    $edit->call('returnToDetails')->assertSet('showReview', false)->assertSet('address', 'Unsaved review address');
});

it('recalculates age when an edited birthdate changes without writing until save', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-28 16:15:00 UTC'));
    $patient = PatientInformation::factory()->create(['birthdate' => '1990-01-01', 'age' => 36]);

    Livewire::actingAs(User::factory()->create())->test(EditPatient::class, ['patient' => $patient])
        ->set('birthdate', '2000-10-30')->assertSet('age', 25);
    expect($patient->fresh()->birthdate)->toBe('1990-01-01')->and($patient->fresh()->age)->toBe(36);
});
