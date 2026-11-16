<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use App\Services\PatientRegistration;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    use PatientHouseholdContext;

    #[Locked]
    public bool $embedded = false;

    #[Locked]
    public int $patientId;

    #[Locked]
    public string $backUrl;

    public function mount(PatientInformation $record, ?int $familyHouseholdId = null, bool $embedded = false): void
    {
        Gate::authorize('queue-view');

        $this->patientId = $record->patient_id;
        $this->familyHouseholdId = $familyHouseholdId;
        $this->embedded = $embedded;
        $patient = $this->contextPatient();
        $this->backUrl = $familyHouseholdId === null ? route('medical-records.index', request()->only(['search', 'fromDate', 'toDate', 'page'])) : route('families.show', $familyHouseholdId);
        if ($familyHouseholdId !== null) {
            app(PatientRegistration::class)->audit($patient, 'family-member-record-accessed', ['household_id' => $familyHouseholdId], $familyHouseholdId);
        }
    }

    public function render(): View
    {
        Gate::authorize('queue-view');

        $patient = $this->contextPatientQuery()->with([
            'household',
            'medicalRecords' => function (HasMany $records): void {
                $records->with(['handledBy', 'vitalSign' => function (BelongsTo $measurements): void {
                    if ($this->familyHouseholdId !== null) {
                        $measurements->where('patient_id', $this->patientId);
                    }
                    $measurements->with('recordedBy');
                }, 'queue', 'symptoms'])
                    ->orderByDesc('consultation_date')->orderByRaw('COALESCE(encounter_at, created_at) DESC')->orderByDesc('medical_record_id');
            },
            'vitalSigns' => function (HasMany $measurements): void {
                $measurements->with('recordedBy')->orderByDesc('recorded_at')->orderByDesc('vital_id');
            },
            'followUps' => function (HasMany $followUps): void {
                $followUps->with(['consultation' => function (BelongsTo $consultation): void {
                    $consultation->where('patient_id', $this->patientId);
                }])->orderByDesc('scheduled_at')->orderByDesc('id');
            },
        ])->findOrFail($this->patientId);

        return view('livewire.medical-record-details', [
            'patient' => $patient,
            'latestQueue' => $patient->queues()->orderByDesc('queue_date')->orderByDesc('queue_id')->first(),
        ]);
    }
}
