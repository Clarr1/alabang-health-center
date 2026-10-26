<?php

use App\Livewire\MedicalRecordsForm;
use App\Livewire\MedicalRecordsTable;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('starts a queue consultation, shows visit vitals, saves, and views a medical record', function () {
    $user = User::factory()->create(['role' => 'Doctor']);
    $patient = PatientInformation::create([
        'first_name' => 'Maria',
        'middle_name' => 'Elena',
        'last_name' => 'Santos',
        'birthdate' => '1998-05-10',
        'age' => 28,
        'sex' => 'Female',
        'address' => 'Sample Address 123',
        'registration_date' => '2026-09-23',
        'status' => 'Active',
    ]);
    $queue = Queue::factory()->create(['patient_id' => $patient->patient_id, 'status' => 'Ready for Consultation']);
    $vitalSign = VitalSign::create([
        'queue_id' => $queue->queue_id,
        'patient_id' => $patient->patient_id,
        'recorded_by' => $user->user_id,
        'blood_pressure' => '120/80',
        'temperature' => 36.5,
        'height' => 160,
        'weight' => 55,
        'bmi' => 21.5,
        'recorded_at' => '2026-09-24 10:30:00',
    ]);

    Livewire::actingAs($user)
        ->test(MedicalRecordsForm::class)
        ->call('startConsultation', $queue->queue_id)->assertSee('Maria')
        ->assertSet('vital_signs_id', $vitalSign->vital_id)
        ->assertSee('120/80')
        ->set('diagnosis', 'Seasonal allergies')
        ->set('treatment', 'Rest and hydration')
        ->set('consultation_notes', 'Follow up as needed.')
        ->set('consultation_date', '2026-09-26')
        ->call('saveMedicalRecord')
        ->assertHasNoErrors();

    $medicalRecord = MedicalRecord::firstOrFail();
    $medicalRecord->update(['prescription' => 'Cetirizine 10 mg']);

    Livewire::actingAs($user)
        ->test(MedicalRecordsTable::class, ['patient_id' => $patient->patient_id])
        ->call('viewMedicalRecord', $medicalRecord->medical_record_id)
        ->assertSet('selectedMedicalRecordId', $medicalRecord->medical_record_id)
        ->assertSet('showMedicalRecordModal', true)
        ->assertSee('Cetirizine 10 mg')
        ->call('closeMedicalRecordModal')
        ->assertSet('showMedicalRecordModal', false);

    $this->assertDatabaseHas('medical_records', [
        'patient_id' => $patient->patient_id,
        'vital_signs_id' => $vitalSign->vital_id,
        'handled_by' => $user->user_id,
        'diagnosis' => 'Seasonal allergies',
        'prescription' => 'Cetirizine 10 mg',
    ]);
});
