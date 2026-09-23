<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PatientInformationForm extends Component
{
    public $patient;

    public $qr_code = '';
    public $first_name = '';
    public $middle_name = '';
    public $last_name = '';
    public $birthdate = '';
    public $age = '';
    public $sex = 'Male';
    public $address = '';
    public $contact_number = '';
    public $emergency_contact = '';
    public $registration_date = '';
    public $status = 'Active';

    public function mount(): void
    {
        $this->registration_date = now()->toDateString();
    }

    public function rules(): array
    {
        return [
            'qr_code' => ['required', 'string', 'max:255', 'unique:patient_information,qr_code'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:150'],
            'sex' => ['required', 'in:Male,Female'],
            'address' => ['required', 'string'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'registration_date' => ['required', 'date'],
            'status' => ['required', 'string', 'max:50'],
        ];
    }

    public function savePatient(): void
    {
        $this->validate();

        $patient = PatientInformation::create([
            'qr_code' => $this->qr_code,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'birthdate' => $this->birthdate,
            'age' => (int) $this->age,
            'sex' => $this->sex,
            'address' => $this->address,
            'contact_number' => $this->contact_number,
            'emergency_contact' => $this->emergency_contact,
            'registration_date' => $this->registration_date,
            'status' => $this->status,
        ]);

        $this->patient = $patient;

        session()->flash('success', 'Patient information saved successfully. You can now record vital signs.');

        $this->redirect(route('patient.vital-signs-form', ['patient_id' => $patient->patient_id]), navigate: true);
    }

    public function render()
    {
        return view('livewire.patient-information-form');
    }
}
