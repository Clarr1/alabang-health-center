<?php

namespace App\Livewire;

use App\Models\MedicalRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class MedicalRecordDetails extends Component
{
    #[Locked]
    public int $medicalRecordId;

    #[Locked]
    public string $backUrl;

    public function mount(MedicalRecord $record): void
    {
        Gate::authorize('queue-view');

        $this->medicalRecordId = $record->medical_record_id;
        $this->backUrl = route('medical-records.index', request()->only(['search', 'fromDate', 'toDate', 'page']));
    }

    public function render(): View
    {
        Gate::authorize('queue-view');

        return view('livewire.medical-record-details', [
            'record' => MedicalRecord::with(['patient', 'handledBy', 'vitalSign.recordedBy'])
                ->findOrFail($this->medicalRecordId),
        ]);
    }
}
