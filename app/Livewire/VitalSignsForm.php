<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use App\Models\VitalSign;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VitalSignsForm extends Component
{
    public $patient;
    public $patient_id;
    public $patientSearch = '';

    public $blood_pressure = '';
    public $temperature = '';
    public $pulse_rate = '';
    public $respiratory_rate = '';
    public $height = '';
    public $weight = '';
    public $bmi = '';
    public $recorded_at = '';

    public function mount($patient_id = null): void
    {
        if ($patient_id !== null) {
            $this->selectPatient($patient_id);
        }

        $this->recorded_at = now()->toDateString();
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patient_information,patient_id'],
            'blood_pressure' => ['nullable', 'string', 'max:20'],
            'temperature' => ['nullable', 'numeric', 'min:25', 'max:45'],
            'pulse_rate' => ['nullable', 'integer', 'min:0', 'max:220'],
            'respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:80'],
            'height' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'weight' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'bmi' => ['nullable', 'numeric', 'min:5', 'max:100'],
            'recorded_at' => ['required', 'date'],
        ];
    }

    public function updatedHeight(): void
    {
        $this->calculateBmi();
    }

    public function updatedWeight(): void
    {
        $this->calculateBmi();
    }

    public function getBmiCategoryProperty(): string
    {
        if ($this->bmi === '' || $this->bmi === null) {
            return '---';
        }

        $bmi = (float) $this->bmi;

        if ($bmi < 18.5) {
            return 'Underweight';
        }

        if ($bmi < 25) {
            return 'Normal';
        }

        if ($bmi < 30) {
            return 'Overweight';
        }

        return 'Obese';
    }

    public function selectPatient(int $patientId): void
    {
        $this->patient = PatientInformation::findOrFail($patientId);
        $this->patient_id = $this->patient->patient_id;
        $this->patientSearch = '';
    }

    public function getPatientResultsProperty()
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
                    ->orWhere('patient_id', 'like', "%{$search}%");
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(8)
            ->get();
    }

    public function saveVitalSigns(): void
    {
        $this->validate();
        $this->calculateBmi();

        VitalSign::create([
            'patient_id' => $this->patient_id,
            'recorded_by' => Auth::id(),
            'blood_pressure' => $this->blood_pressure ?: null,
            'temperature' => $this->temperature !== '' ? $this->temperature : null,
            'pulse_rate' => $this->pulse_rate !== '' ? (int) $this->pulse_rate : null,
            'respiratory_rate' => $this->respiratory_rate !== '' ? (int) $this->respiratory_rate : null,
            'height' => $this->height !== '' ? $this->height : null,
            'weight' => $this->weight !== '' ? $this->weight : null,
            'bmi' => $this->bmi !== '' ? $this->bmi : null,
            'recorded_at' => $this->recorded_at ?: now(),
        ]);

        session()->flash('success', 'Vital signs saved successfully.');

        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.vital-signs-form');
    }

    private function calculateBmi(): void
    {
        if ($this->height !== '' && $this->weight !== '' && (float) $this->height > 0) {
            $heightInMeters = (float) $this->height / 100;
            $this->bmi = round((float) $this->weight / ($heightInMeters * $heightInMeters), 1);
            return;
        }

        $this->bmi = '';
    }

    private function resetForm(): void
    {
        $this->blood_pressure = '';
        $this->temperature = '';
        $this->pulse_rate = '';
        $this->respiratory_rate = '';
        $this->height = '';
        $this->weight = '';
        $this->bmi = '';
        $this->recorded_at = now()->toDateString();
    }
}
