<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PatientTable extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedPatientId = null;
    public $showPatientModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function patients(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return PatientInformation::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('patient_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10);
    }

    public function viewPatient(int $patientId): void
    {
        if (! PatientInformation::whereKey($patientId)->exists()) {
            return;
        }

        $this->selectedPatientId = $patientId;
        $this->showPatientModal = true;
    }

    public function closePatientModal(): void
    {
        $this->selectedPatientId = null;
        $this->showPatientModal = false;
        unset($this->selectedPatient);
    }

    #[Computed]
    public function selectedPatient(): ?PatientInformation
    {
        if (! $this->selectedPatientId) {
            return null;
        }

        return PatientInformation::with([
            'vitalSigns.recordedBy',
            'medicalRecords.handledBy',
            'medicalRecords.vitalSign',
        ])->find($this->selectedPatientId);
    }

    public function render()
    {
        return view('livewire.patient-table');
    }
}