<?php

namespace App\Livewire;

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MedicalRecordsForm extends Component
{
    public $patient;
    public $patient_id;
    public $patientSearch = '';
    public $vital_signs_id = '';
    public $diagnosis = '';
    public $prescription = '';
    public $treatment = '';
    public $consultation_notes = '';
    public $consultation_date = '';
    public $selectedMedicalRecordId = null;
    public $showMedicalRecordModal = false;

    public function mount(?int $patient_id = null): void
    {
        $this->consultation_date = now()->toDateString();

        if ($patient_id !== null) {
            $this->selectPatient($patient_id);
        }
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patient_information,patient_id'],
            'vital_signs_id' => ['required', 'integer', 'exists:vital_signs,vital_id'],
            'diagnosis' => ['required', 'string'],
            'treatment' => ['nullable', 'string'],
            'consultation_notes' => ['nullable', 'string'],
            'consultation_date' => ['required', 'date'],
        ];
    }

    public function selectPatient(int $patientId): void
    {
        $this->patient = PatientInformation::findOrFail($patientId);
        $this->patient_id = $this->patient->patient_id;
        $this->patientSearch = '';
        $this->vital_signs_id = $this->patient->vitalSigns()->latest('recorded_at')->latest('vital_id')->value('vital_id') ?? '';
        $this->closeMedicalRecordModal();
    }

    public function selectVitalSign(int $vitalId): void
    {
        if ($this->patient && $this->patient->vitalSigns()->whereKey($vitalId)->exists()) {
            $this->vital_signs_id = $vitalId;
        }
    }

    public function viewMedicalRecord(int $medicalRecordId): void
    {
        if (! $this->patient || ! $this->patient->medicalRecords()->whereKey($medicalRecordId)->exists()) {
            return;
        }

        $this->selectedMedicalRecordId = $medicalRecordId;
        $this->showMedicalRecordModal = true;
    }

    public function closeMedicalRecordModal(): void
    {
        $this->selectedMedicalRecordId = null;
        $this->showMedicalRecordModal = false;
        unset($this->selectedMedicalRecord);
    }

    #[Computed]
    public function patientResults()
    {
        $search = trim($this->patientSearch);

        if (mb_strlen($search) < 2) {
            return collect();
        }

        return PatientInformation::query()
            ->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('qr_code', 'like', "%{$search}%")
                    ->orWhere('patient_id', 'like', "%{$search}%")
                    ->orWhere('contact_number', 'like', "%{$search}%");
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function vitalSigns()
    {
        return $this->patient
            ? $this->patient->vitalSigns()->with('recordedBy')->latest('recorded_at')->latest('vital_id')->get()
            : collect();
    }

    #[Computed]
    public function medicalRecords()
    {
        return $this->patient
            ? $this->patient->medicalRecords()->with('handledBy', 'vitalSign')->latest('consultation_date')->latest('medical_record_id')->get()
            : collect();
    }

    #[Computed]
    public function selectedMedicalRecord(): ?MedicalRecord
    {
        if (! $this->patient || ! $this->selectedMedicalRecordId) {
            return null;
        }

        return $this->patient->medicalRecords()
            ->with(['patient', 'handledBy', 'vitalSign.recordedBy'])
            ->find($this->selectedMedicalRecordId);
    }

    public function saveMedicalRecord(): void
    {
        $this->validate();

        if (! $this->patient || ! $this->patient->vitalSigns()->whereKey($this->vital_signs_id)->exists()) {
            $this->addError('vital_signs_id', 'Select a vital signs record for this consultation.');
            return;
        }

        MedicalRecord::create([
            'patient_id' => $this->patient_id,
            'vital_signs_id' => $this->vital_signs_id,
            'handled_by' => Auth::id(),
            'diagnosis' => $this->diagnosis,
            'prescription' => $this->prescription ?: null,
            'treatment' => $this->treatment ?: null,
            'consultation_notes' => $this->consultation_notes ?: null,
            'consultation_date' => $this->consultation_date,
            'created_at' => now(),
        ]);

        session()->flash('success', 'Medical record saved successfully.');
        $this->dispatch('medical-record-saved');
    }

    public function resetForm(): void
    {
        $patientId = $this->patient_id;
        $patient = $this->patient;
        $vitalSignsId = $this->vital_signs_id;

        $this->reset([
            'patientSearch',
            'diagnosis',
            'prescription',
            'treatment',
            'consultation_notes',
        ]);

        $this->patient_id = $patientId;
        $this->patient = $patient;
        $this->vital_signs_id = $vitalSignsId;
        $this->consultation_date = now()->toDateString();
        unset($this->medicalRecords);
    }

    public function render()
    {
        return view('livewire.medical-records-form');
    }
}