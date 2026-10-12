<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PatientInformationForm extends Component
{
    public $patient;

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
        $this->registration_date = now('Asia/Manila')->toDateString();
    }

    public function rules(): array
    {
        return [
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
        Gate::authorize('queue-register');
        $this->validate();

        try {
            DB::transaction(function (): void {
                $this->patient = PatientInformation::create([
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

            }, 5);
        } catch (QueryException $exception) {
            report($exception);
            $this->patient = null;
            $this->addError('queue', 'Unable to register this visit. Please try again.');

            return;
        }

        session()->flash('success', 'Patient registered successfully. Select an assigned queue number to add this visit.');
        $this->dispatch(
            'patient-information-saved',
            redirectUrl: route('queues.index', ['patient' => $this->patient->patient_id]),
        );
    }

    public function resetForm(): void
    {
        $this->reset([
            'first_name',
            'middle_name',
            'last_name',
            'birthdate',
            'age',
            'address',
            'contact_number',
            'emergency_contact',
        ]);

        $this->sex = 'Male';
        $this->registration_date = now('Asia/Manila')->toDateString();
        $this->status = 'Active';
        $this->patient = null;
    }

    public function render(): View
    {
        return view('livewire.patient-information-form');
    }
}
