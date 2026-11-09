<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

        $patient = PatientInformation::with([
            'medicalRecords' => function (HasMany $records): void {
                $records->with(['handledBy', 'vitalSign.recordedBy', 'queue', 'symptoms'])
                    ->orderByDesc('consultation_date')->orderByDesc('medical_record_id');
            },
            'vitalSigns' => function (HasMany $measurements): void {
                $measurements->with('recordedBy')->orderByDesc('recorded_at')->orderByDesc('vital_id');
            },
        ])->findOrFail($this->patientId);

        return view('livewire.medical-record-details', [
            'patient' => $patient,
            'latestQueue' => $patient->queues()->orderByDesc('queue_date')->orderByDesc('queue_id')->first(),
        ]);
    }
}
