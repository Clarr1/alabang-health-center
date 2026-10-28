<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Medical Record Details')]
class MedicalRecordDetails extends Component
{
    #[Locked]
    public int $patientId;

    #[Locked]
    public string $backUrl;

    public function mount(PatientInformation $record): void
    {
        Gate::authorize('queue-view');

        $this->patientId = $record->patient_id;
        $this->backUrl = route('medical-records.index', request()->only(['search', 'fromDate', 'toDate', 'page']));
    }

    public function render(): View
    {
        Gate::authorize('queue-view');

        return view('livewire.medical-record-details', [
            'patient' => PatientInformation::with([
                'medicalRecords' => fn ($query) => $query->with(['handledBy', 'vitalSign.recordedBy'])
                    ->orderByDesc('consultation_date')->orderByDesc('medical_record_id'),
            ])->findOrFail($this->patientId),
        ]);
    }
}
