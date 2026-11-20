<?php

namespace Database\Seeders;

use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Symptom;
use App\Models\User;
use Illuminate\Database\Seeder;

class MedicalRecordSeeder extends Seeder
{
    public function run(): void
    {
        $doctor = User::where('email', 'doctor@example.test')->sole();
        foreach (PatientInformation::where('address', 'like', PatientInformationSeeder::ADDRESS_PREFIX.'%')->get() as $patient) {
            $vitals = $patient->vitalSigns()->oldest('recorded_at')->firstOrFail();
            $diagnosis = Diagnosis::where('normalized_name', Diagnosis::normalizeName('Acute upper respiratory infection'))->sole();
            $record = MedicalRecord::firstOrCreate(['vital_signs_id' => $vitals->getKey()], [
                'patient_id' => $patient->getKey(), 'handled_by' => $doctor->getKey(),
                'consultation_date' => $vitals->recorded_at->copy()->timezone('Asia/Manila')->toDateString(),
                'encounter_at' => $vitals->recorded_at->copy()->addMinutes(15),
                'created_at' => $vitals->recorded_at->copy()->addMinutes(20),
                'diagnosis_id' => $diagnosis->getKey(), 'diagnosis' => $diagnosis->name,
                'chief_complaint' => 'Mild cough and sore throat for two days without fever.',
                'custom_symptoms' => [], 'assessment_notes' => 'Simulated uncomplicated respiratory illness; stable vital signs.',
                'treatment' => 'FICTIONAL care plan: rest, oral fluids, and reassessment if symptoms persist.',
                'consultation_notes' => 'FICTIONAL development record. Never use for patient care.',
                'follow_up_date' => null,
            ]);
            if ($record->wasRecentlyCreated) {
                $record->symptoms()->sync(Symptom::whereIn('name', ['Cough', 'Sore Throat'])->get()->mapWithKeys(
                    fn (Symptom $symptom): array => [$symptom->getKey() => ['symptom_name' => $symptom->name]]
                )->all());
            }
        }
    }
}
