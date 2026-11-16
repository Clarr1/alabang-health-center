<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use App\Models\VitalSign;
use App\Services\IndividualTreatmentRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PatientVitalSignForm extends PatientInformationForm
{
    use VitalSignFields;

    public string $status = 'Active';

    public function mount(): void
    {
        Gate::authorize('queue-register');
        parent::mount();
        $this->recorded_at = now('Asia/Manila')->format('Y-m-d\TH:i');
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...$this->vitalSignRules(),
            'status' => ['required', 'in:Active,Inactive,Follow-up'],
        ];
    }

    public function save(): void
    {
        Gate::authorize('queue-register');
        if ($this->registeredPatientId !== null) {
            return;
        }
        $this->calculateBmi();
        $validated = $this->validate();

        try {
            $patient = DB::transaction(function () use ($validated): PatientInformation {
                $patient = PatientInformation::create(array_intersect_key($validated, parent::rules()) + ['status' => $this->status]);
                $vital = VitalSign::create([
                    ...$this->vitalSignValues(),
                    'patient_id' => $patient->patient_id,
                    'recorded_by' => auth()->id(),
                    'recorded_at' => now(),
                ]);

                IndividualTreatmentRecord::audit((int) $patient->patient_id, null, 'vital-signs-recorded', $vital->toArray());

                return $patient;
            }, 5);
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('patient', 'Unable to save the patient and vital signs. Please try again.');

            return;
        }

        $this->resetForm();
        $this->registeredPatientId = $patient->patient_id;
        session()->flash('success', 'Patient information and vital signs saved successfully.');
        $this->dispatch('patient-information-saved');
    }

    public function savePatient(): void
    {
        $this->save();
    }

    public function resetForm(): void
    {
        parent::resetForm();
        $this->reset('status', 'blood_pressure', 'temperature', 'pulse_rate', 'respiratory_rate', 'height', 'weight', 'bmi', 'oxygen_saturation', 'waistline', 'nutritional_status', 'nutrition_assessment_method');
        $this->recorded_at = now('Asia/Manila')->format('Y-m-d\TH:i');
    }

    public function render(): View
    {
        Gate::authorize('queue-register');

        return view('livewire.patient-vital-sign-form');
    }
}
