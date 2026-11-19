<?php

namespace App\Livewire;

use App\Models\Household;
use App\Models\PatientProfileAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Family Record')]
class FamilyRecord extends Component
{
    #[Locked]
    public int $householdId;

    #[Locked]
    public ?int $selectedMemberId = null;

    #[Locked]
    public string $backUrl;

    public function mount(Household $household): void
    {
        Gate::authorize('queue-view');
        $this->householdId = $household->id;
        $this->backUrl = route('families.index', request()->only(['search', 'memberFilter', 'page']));
        PatientProfileAudit::create(['household_id' => $household->id, 'user_id' => auth()->id(), 'action' => 'family-record-accessed', 'details' => ['family_number' => $household->family_number]]);
    }

    public function selectMember(int $patientId): void
    {
        Gate::authorize('medical-records-view');
        $member = Household::findOrFail($this->householdId)->members()->findOrFail($patientId);
        $this->selectedMemberId = $member->patient_id;
    }

    public function closeMember(): void
    {
        Gate::authorize('queue-view');
        $this->selectedMemberId = null;
    }

    public function render(): View
    {
        Gate::authorize('queue-view');
        $household = Household::findOrFail($this->householdId);
        $members = $household->members()->select(['patient_id', 'household_id', 'first_name', 'middle_name', 'last_name', 'birthdate', 'sex', 'patient_category', 'household_relationship', 'status'])
            ->withCount(Gate::allows('medical-records-view') ? ['medicalRecords', 'vitalSigns', 'immunizationDoses', 'followUps'] : [])
            ->orderBy('last_name')->orderBy('first_name')->orderBy('patient_id')->get();
        $selectedMember = ! Gate::allows('medical-records-view') || $this->selectedMemberId === null ? null : $household->members()->findOrFail($this->selectedMemberId);

        return view('livewire.family-record', compact('household', 'members', 'selectedMember'));
    }
}
