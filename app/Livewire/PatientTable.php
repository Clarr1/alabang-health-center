<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Patient Record')]
class PatientTable extends Component
{
    use WithPagination;

    public $search = '';

    public string $recordStatus = '';

    public string $sex = '';

    public string $lastVisit = '';

    public $selectedPatientId = null;

    public $showPatientModal = false;

    #[Locked]
    public ?int $cardPatientId = null;

    public bool $showCardModal = false;

    public function generateIdCard(int $patientId): void
    {
        Gate::authorize('patient-records-view');
        abort_unless(auth()->check(), 403);
        PatientInformation::findOrFail($patientId);
        $this->cardPatientId = $patientId;
        $this->showCardModal = true;
    }

    public function closeCardModal(): void
    {
        Gate::authorize('patient-records-view');
        $this->reset('cardPatientId', 'showCardModal');
    }

    public function updated(string $property): void
    {
        Gate::authorize('patient-records-view');
        if (in_array($property, ['search', 'recordStatus', 'sex', 'lastVisit'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        Gate::authorize('patient-records-view');
        $this->reset('search', 'recordStatus', 'sex', 'lastVisit');
        $this->resetPage();
    }

    /** @return Collection<int, string> */
    #[Computed]
    public function recordStatuses(): Collection
    {
        Gate::authorize('patient-records-view');

        return PatientInformation::query()->whereNotNull('status')->where('status', '!=', '')
            ->distinct()->orderBy('status')->pluck('status');
    }

    #[Computed]
    public function activePatientCount(): int
    {
        Gate::authorize('patient-records-view');

        return PatientInformation::where('status', 'Active')->count();
    }

    #[Computed]
    public function patients(): LengthAwarePaginator
    {
        Gate::authorize('patient-records-view');
        $search = trim($this->search);
        $visits = PatientInformation::query()
            ->withMax('vitalSigns as last_vital_at', 'recorded_at')
            ->withMax('medicalRecords as last_consultation_at', 'consultation_date');
        $lastVisitExpression = 'CASE WHEN last_vital_at IS NULL THEN last_consultation_at
            WHEN last_consultation_at IS NULL THEN last_vital_at
            WHEN last_vital_at >= last_consultation_at THEN last_vital_at ELSE last_consultation_at END';
        $visitCutoff = match ($this->lastVisit) {
            'today' => now('Asia/Manila')->startOfDay(),
            'week' => now('Asia/Manila')->startOfDay()->subDays(6),
            'month' => now('Asia/Manila')->startOfDay()->subDays(29),
            default => null,
        };

        return PatientInformation::query()
            ->fromSub($visits, 'patient_information')
            ->with('household:id,family_number,label')
            ->select('patient_information.*')
            ->selectRaw("{$lastVisitExpression} AS last_visit_at")
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('patient_id', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere(function (Builder $query) use ($search): void {
                            foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $namePart) {
                                $query->where(function (Builder $query) use ($namePart): void {
                                    $query->where('first_name', 'like', "%{$namePart}%")
                                        ->orWhere('middle_name', 'like', "%{$namePart}%")
                                        ->orWhere('last_name', 'like', "%{$namePart}%");
                                });
                            }
                        });
                });
            })
            ->when($this->recordStatus !== '', fn (Builder $query): Builder => $query->where('status', $this->recordStatus))
            ->when($this->sex !== '', fn (Builder $query): Builder => $query->where('sex', $this->sex))
            ->when($visitCutoff !== null, fn (Builder $query): Builder => $query
                ->whereRaw("{$lastVisitExpression} >= ?", [$visitCutoff->toDateString()])
                ->whereRaw("{$lastVisitExpression} < ?", [now('Asia/Manila')->addDay()->startOfDay()->toDateString()]))
            ->when($this->lastVisit === 'never', fn (Builder $query): Builder => $query->whereRaw("{$lastVisitExpression} IS NULL"))
            ->orderByDesc('last_visit_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('patient_id')
            ->paginate(10);
    }

    public function viewPatient(int $patientId): void
    {
        Gate::authorize('patient-records-view');
        if (! PatientInformation::whereKey($patientId)->exists()) {
            return;
        }

        $this->selectedPatientId = $patientId;
        $this->showPatientModal = true;
    }

    public function closePatientModal(): void
    {
        Gate::authorize('patient-records-view');
        $this->selectedPatientId = null;
        $this->showPatientModal = false;
        unset($this->selectedPatient);
    }

    #[Computed]
    public function selectedPatient(): ?PatientInformation
    {
        Gate::authorize('patient-records-view');
        if (! $this->selectedPatientId) {
            return null;
        }

        return PatientInformation::with(Gate::allows('medical-records-view') ? [
            'vitalSigns.recordedBy', 'medicalRecords.handledBy', 'medicalRecords.vitalSign',
        ] : [])->find($this->selectedPatientId);
    }

    public function render()
    {
        Gate::authorize('patient-records-view');

        return view('livewire.patient-table');
    }
}
