<?php

namespace App\Livewire;

use App\Models\PatientProfileAudit;
use App\Services\PatientRegistration;
use App\Services\PatientRegistrationSchema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PatientProfileForm extends Component
{
    use PatientHouseholdContext;
    use RegistrationProfileFields;

    #[Locked]
    public int $patientId;

    #[Locked]
    public string $birthdate;

    public bool $showReview = true;

    public bool $editing = false;

    #[Locked]
    public bool $showHistory = false;

    public function recordDoseUpdate(int $doseId): void
    {
        Gate::authorize('patient-profile-manage');
        $patient = $this->contextPatient();
        $dose = $patient->immunizationDoses()->findOrFail($doseId);
        if (! $this->editing) {
            $this->editProfile();
        }
        if (count($this->newDoses) >= 30) {
            $this->addError('newDoses', 'Save the current entries before adding another dose update.');

            return;
        }
        $this->addDose();
        $index = array_key_last($this->newDoses);
        if ($index === null) {
            return;
        }
        $knownVaccine = in_array($dose->vaccine_name, PatientRegistrationSchema::VACCINES, true);
        $knownSchedule = in_array($dose->schedule, PatientRegistrationSchema::SCHEDULES, true);
        $this->newDoses[$index] = [
            'vaccine_name' => $knownVaccine ? $dose->vaccine_name : 'Others', 'other_vaccine' => $knownVaccine ? '' : $dose->vaccine_name,
            'schedule' => $knownSchedule ? $dose->schedule : 'Other', 'other_schedule' => $knownSchedule ? '' : $dose->schedule,
            'dose' => $dose->dose, 'status' => $dose->status, 'administered_at' => $dose->administered_at?->toDateString() ?? '',
            'administered_by' => $dose->administered_by, 'remarks' => $dose->remarks, 'supersedes_id' => $dose->id,
        ];
    }

    public function mount(int $patientId, ?int $familyHouseholdId = null): void
    {
        Gate::authorize('patient-profile-manage');
        $this->patientId = $patientId;
        $this->familyHouseholdId = $familyHouseholdId;
        $patient = $this->contextPatient();
        $this->birthdate = $patient->birthdate;
        $this->initializeRegistrationProfile($patient);
    }

    public function editProfile(): void
    {
        Gate::authorize('patient-profile-manage');
        $patient = $this->contextPatient();
        $this->birthdate = $patient->birthdate;
        unset($this->profileSections);
        $this->initializeRegistrationProfile($patient);
        $this->editing = true;
        $this->showReview = false;
    }

    public function updated(string $property): void
    {
        if ($this->editing && $property !== 'showHistory') {
            $this->showReview = false;
        }
    }

    public function reviewProfile(): void
    {
        Gate::authorize('patient-profile-manage');
        $this->validateRegistrationProfile();
        $this->showReview = true;
    }

    public function cancelEdit(): void
    {
        Gate::authorize('patient-profile-manage');
        $patient = $this->contextPatient();
        $this->birthdate = $patient->birthdate;
        unset($this->profileSections);
        $this->initializeRegistrationProfile($patient);
        $this->editing = false;
        $this->showReview = true;
        $this->resetValidation();
    }

    public function saveProfile(): void
    {
        Gate::authorize('patient-profile-manage');
        $this->validateRegistrationProfile();
        try {
            $patient = $this->contextPatient();
            $this->saveRegistrationProfile($patient);
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('profile', 'Unable to save this profile. Please try again.');

            return;
        }
        if ($this->familyHouseholdId !== null && $patient->fresh()->household_id !== $this->familyHouseholdId) {
            $this->redirectRoute('families.show', ['household' => $this->familyHouseholdId], navigate: true);

            return;
        }
        $this->cancelEdit();
        session()->flash('profile-success', 'Patient profile saved successfully.');
    }

    public function viewHistory(): void
    {
        Gate::authorize('patient-profile-manage');
        $this->showHistory = ! $this->showHistory;
        if ($this->showHistory) {
            app(PatientRegistration::class)->audit($this->contextPatient(), 'profile-history-accessed');
        }
    }

    public function render(): View
    {
        Gate::authorize('patient-profile-manage');
        $patient = $this->contextPatientQuery()->with(['household.members', 'immunizationDoses', 'conditions', 'registrationProfile.preparer'])->findOrFail($this->patientId);
        $history = collect();
        if ($this->showHistory) {
            $allowed = array_keys(array_merge(...array_values(app(PatientRegistration::class)->sections($this->birthdate, true))));
            $history = $patient->profileAudits()->where('action', 'profile-updated')->latest('id')->get()->map(function (PatientProfileAudit $audit) use ($allowed): array {
                return ['date' => $audit->created_at, 'user_id' => $audit->user_id, 'values' => array_intersect_key($audit->details['before'] ?? [], array_flip($allowed))];
            });
        }

        return view('livewire.patient-profile-form', compact('patient', 'history'));
    }
}
