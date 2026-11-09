<?php

namespace App\Livewire;

use App\Models\Diagnosis;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Diagnosis Management')]
class DiagnosisManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    #[Locked]
    public ?int $editingDiagnosisId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public string $success = '';

    public function mount(): void
    {
        Gate::authorize('diagnoses-manage');
    }

    public function updated(string $property): void
    {
        Gate::authorize('diagnoses-manage');
        if (in_array($property, ['search', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function createDiagnosis(): void
    {
        Gate::authorize('diagnoses-manage');
        $this->reset('editingDiagnosisId', 'name', 'code', 'description');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function editDiagnosis(int $diagnosisId): void
    {
        Gate::authorize('diagnoses-manage');
        $this->createDiagnosis();
        $diagnosis = Diagnosis::findOrFail($diagnosisId);
        $this->editingDiagnosisId = $diagnosis->id;
        $this->name = $diagnosis->name;
        $this->code = $diagnosis->code ?? '';
        $this->description = $diagnosis->description ?? '';
    }

    public function closeForm(): void
    {
        Gate::authorize('diagnoses-manage');
        $this->showForm = false;
    }

    public function saveDiagnosis(): void
    {
        Gate::authorize('diagnoses-manage');
        $this->name = trim($this->name);
        $this->validate([
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Diagnosis::where('normalized_name', Diagnosis::normalizeName($value))->when($this->editingDiagnosisId !== null, fn (Builder $query): Builder => $query->whereKeyNot($this->editingDiagnosisId))->exists()) {
                    $fail('This diagnosis name is already in use.');
                }
            }],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            DB::transaction(function (): void {
                $diagnosis = $this->editingDiagnosisId === null ? new Diagnosis : Diagnosis::lockForUpdate()->findOrFail($this->editingDiagnosisId);
                $diagnosis->fill(['name' => $this->name, 'code' => $this->code ?: null, 'description' => $this->description ?: null]);
                if (! $diagnosis->exists) {
                    $diagnosis->review_status = 'approved';
                    $diagnosis->is_active = true;
                }
                $diagnosis->save();
            });
        } catch (UniqueConstraintViolationException) {
            $this->addError('name', 'This diagnosis name is already in use.');

            return;
        }
        $this->showForm = false;
        $this->success = 'Diagnosis saved. Historical medical record wording is preserved.';
        unset($this->diagnoses);
    }

    public function setActive(int $diagnosisId, bool $active): void
    {
        Gate::authorize('diagnoses-manage');
        DB::transaction(function () use ($diagnosisId, $active): void {
            $diagnosis = Diagnosis::lockForUpdate()->findOrFail($diagnosisId);
            abort_unless($diagnosis->review_status === 'approved', 409);
            $diagnosis->update(['is_active' => $active]);
        });
        $this->success = 'Diagnosis status updated.';
        unset($this->diagnoses);
    }

    public function reviewDiagnosis(int $diagnosisId, string $decision): void
    {
        Gate::authorize('diagnoses-manage');
        validator(['decision' => $decision], ['decision' => ['required', Rule::in(['approved', 'rejected'])]])->validate();
        DB::transaction(function () use ($diagnosisId, $decision): void {
            $diagnosis = Diagnosis::lockForUpdate()->findOrFail($diagnosisId);
            abort_unless($diagnosis->review_status === 'pending', 409);
            $diagnosis->update(['review_status' => $decision, 'is_active' => $decision === 'approved']);
        });
        $this->success = 'Proposed diagnosis reviewed. Original consultation wording is preserved.';
        unset($this->diagnoses);
    }

    #[Computed]
    public function diagnoses(): LengthAwarePaginator
    {
        Gate::authorize('diagnoses-manage');

        return Diagnosis::query()->where('name', 'like', '%'.mb_substr(trim($this->search), 0, 255).'%')
            ->when(in_array($this->statusFilter, ['pending', 'approved', 'rejected'], true), fn (Builder $query): Builder => $query->where('review_status', $this->statusFilter))
            ->withCount('medicalRecords')->orderBy('name')->paginate(10);
    }

    public function render(): View
    {
        Gate::authorize('diagnoses-manage');

        return view('livewire.diagnosis-management');
    }
}
