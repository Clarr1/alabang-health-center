<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

trait PatientHouseholdContext
{
    #[Locked]
    public ?int $familyHouseholdId = null;

    protected function contextPatientQuery(): Builder
    {
        if ($this->familyHouseholdId !== null) {
            Gate::authorize('queue-view');
        }

        return PatientInformation::query()->when($this->familyHouseholdId !== null, fn (Builder $query): Builder => $query->where('household_id', $this->familyHouseholdId));
    }

    protected function contextPatient(): PatientInformation
    {
        return $this->contextPatientQuery()->findOrFail($this->patientId);
    }
}
