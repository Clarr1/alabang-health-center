<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Patient Registration')]
class PatientInformationForm extends Component
{
    use PatientFormFields;

    public $patient;

    #[Locked]
    public ?int $registeredPatientId = null;

    #[Locked]
    public int $registrationStep = 1;

    #[Locked]
    public ?int $registrationHouseholdId = null;

    public function continueRegistration(): void
    {
        Gate::authorize('queue-register');
        if ($this->registrationStep >= 4) {
            $this->reviewDetails();

            return;
        }
        if ($this->registrationStep === 1) {
            $this->validate();
            $this->validateAutomaticPatientCategory();
        } else {
            $this->validateRegistrationHousehold();
            $this->validateRegistrationProfile(false);
        }
        $this->registrationStep++;
    }

    public function submitRegistration(): void
    {
        if ($this->showReview) {
            $this->savePatient();
        } else {
            $this->continueRegistration();
        }
    }

    public function goToRegistrationStep(int $step): void
    {
        Gate::authorize('queue-register');
        abort_unless($step >= 1 && $step < $this->registrationStep, 422);
        $this->registrationStep = $step;
        $this->showReview = false;
    }

    public function reviewDetails(): void
    {
        Gate::authorize('queue-register');
        $this->validate();
        $this->validateAutomaticPatientCategory();
        $this->validateRegistrationHousehold();
        $this->validateRegistrationProfile();
        $this->showReview = true;
        $this->registrationStep = 5;
    }

    public function returnToDetails(): void
    {
        Gate::authorize('queue-register');
        $this->showReview = false;
        $this->registrationStep = 4;
    }

    private function validateRegistrationHousehold(): void
    {
        if ($this->registrationHouseholdId === null) {
            return;
        }
        validator($this->only(['householdMode', 'householdId']), [
            'householdMode' => ['required', Rule::in(['existing'])],
            'householdId' => ['required', 'integer', Rule::in([$this->registrationHouseholdId]), 'exists:households,id'],
        ], [
            'householdMode.in' => 'Use the preselected household when adding a family member.',
            'householdId.in' => 'Use the preselected household when adding a family member.',
        ])->validate();
    }

    public function mount(): void
    {
        $this->registration_date = now('Asia/Manila')->toDateString();
        if (request()->has('household')) {
            Gate::authorize('queue-register');
            $validated = validator(['household' => request()->query('household')], ['household' => ['required', 'integer', 'exists:households,id']])->validate();
            $this->registrationHouseholdId = (int) $validated['household'];
            $this->selectHousehold($this->registrationHouseholdId);
        }
    }

    public function savePatient(): void
    {
        Gate::authorize('queue-register');
        if ($this->registeredPatientId !== null) {
            return;
        }

        $this->validate();
        $this->validateAutomaticPatientCategory();
        $this->validateRegistrationHousehold();
        $this->validateRegistrationProfile();

        try {
            DB::transaction(function (): void {
                $this->patient = PatientInformation::create([
                    'first_name' => $this->first_name,
                    'middle_name' => $this->middle_name,
                    'last_name' => $this->last_name,
                    'birthdate' => $this->birthdate,
                    'sex' => $this->sex,
                    'address' => $this->address,
                    'surveillance_residence' => $this->surveillance_residence,
                    'contact_number' => $this->contact_number,
                    'emergency_contact' => $this->emergency_contact,
                    'registration_date' => $this->registration_date,
                    'status' => 'Active',
                ]);
                $this->saveRegistrationProfile($this->patient);
            }, 5);
        } catch (QueryException $exception) {
            report($exception);
            $this->patient = null;
            $this->addError('queue', 'Unable to register this visit. Please try again.');

            return;
        }

        $registeredPatientId = (int) $this->patient->patient_id;
        $this->resetForm();
        $this->registeredPatientId = $registeredPatientId;

        session()->flash('success', 'Patient registered successfully.');
        $this->dispatch('patient-information-saved');
    }

    public function resetForm(): void
    {
        Gate::authorize('queue-register');
        $this->resetValidation();
        $this->resetRegistrationProfile();
        $this->registrationStep = 1;
        $this->registeredPatientId = null;
        unset($this->registeredPatient);
        session()->forget('success');
        $this->reset([
            'showReview',
            'first_name',
            'middle_name',
            'last_name',
            'birthdate',
            'age',
            'address',
            'surveillance_residence',
            'contact_number',
            'emergency_contact',
        ]);

        $this->sex = 'Male';
        $this->registration_date = now('Asia/Manila')->toDateString();
        $this->patient = null;
        if ($this->registrationHouseholdId !== null) {
            $this->selectHousehold($this->registrationHouseholdId);
        }
    }

    #[Computed]
    public function registeredPatient(): ?PatientInformation
    {
        Gate::authorize('queue-register');

        return $this->registeredPatientId === null ? null : PatientInformation::findOrFail($this->registeredPatientId);
    }

    public function render(): View
    {
        return view('livewire.patient-information-form');
    }
}
