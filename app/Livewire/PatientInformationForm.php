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
    use PatientFormFields;

    public $patient;

    public function mount(): void
    {
        $this->registration_date = now('Asia/Manila')->toDateString();
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
                    'status' => 'Active',
                ]);

            }, 5);
        } catch (QueryException $exception) {
            report($exception);
            $this->patient = null;
            $this->addError('queue', 'Unable to register this visit. Please try again.');

            return;
        }

        $this->resetForm();

        session()->flash('success', 'Patient registered successfully.');
        $this->dispatch('patient-information-saved');
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
        $this->patient = null;
    }

    public function render(): View
    {
        return view('livewire.patient-information-form');
    }
}
