<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class EditPatient extends Component
{
    use PatientFormFields {
        rules as protected patientRules;
    }

    #[Locked]
    public int $patientId;

    public string $status = 'Active';

    public function mount(PatientInformation $patient): void
    {
        Gate::authorize('queue-register');

        $this->patientId = $patient->patient_id;
        $this->fill($patient->only(array_keys($this->rules())));
        $this->calculatePatientAge();
    }

    public function rules(): array
    {
        return [
            ...$this->patientRules(),
            'status' => ['required', 'in:Active,Inactive,Follow-up'],
        ];
    }

    public function saveChanges(): void
    {
        Gate::authorize('queue-register');

        $validated = $this->validate();
        $patient = PatientInformation::findOrFail($this->patientId);

        try {
            $patient->update($validated);
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('patient', 'Unable to update this patient. Please try again.');

            return;
        }

        $this->dispatch('patient-updated', redirectUrl: route('patients.index'));
    }

    public function render(): View
    {
        return view('livewire.edit-patient');
    }
}
