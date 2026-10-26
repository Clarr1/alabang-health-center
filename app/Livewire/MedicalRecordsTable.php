<?php

namespace App\Livewire;

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MedicalRecordsTable extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $fromDate = '';

    #[Url(except: '')]
    public string $toDate = '';

    public function mount(?int $patient_id = null): void
    {
        Gate::authorize('queue-view');
        if ($patient_id !== null) {
            PatientInformation::findOrFail($patient_id);
            $this->search = (string) $patient_id;
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
            $this->resetValidation();
            unset($this->medicalRecords);
        }
    }

    public function clearFilters(): void
    {
        Gate::authorize('queue-view');
        $this->reset('search', 'fromDate', 'toDate');
        $this->resetPage();
        $this->resetValidation();
        unset($this->medicalRecords);
    }

    #[Computed]
    public function medicalRecords(): LengthAwarePaginator
    {
        Gate::authorize('queue-view');
        $toDateRules = ['nullable', 'date_format:Y-m-d'];
        if ($this->fromDate !== '') {
            $toDateRules[] = 'after_or_equal:fromDate';
        }

        $validator = Validator::make([
            'search' => $this->search, 'fromDate' => $this->fromDate, 'toDate' => $this->toDate,
        ], [
            'search' => ['string', 'max:255'],
            'fromDate' => ['nullable', 'date_format:Y-m-d'],
            'toDate' => $toDateRules,
        ], [], ['fromDate' => 'from date', 'toDate' => 'to date']);

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return MedicalRecord::query()->whereRaw('1 = 0')->paginate(10);
        }

        $search = trim($this->search);

        return MedicalRecord::with(['patient', 'handledBy'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                if (ctype_digit($search)) {
                    $query->where('patient_id', $search);

                    return;
                }

                $query->whereHas('patient', function (Builder $query) use ($search): void {
                    foreach (preg_split('/\\s+/', $search) as $term) {
                        $query->where(function (Builder $query) use ($term): void {
                            $query->where('first_name', 'like', "%{$term}%")
                                ->orWhere('middle_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                });
            })
            ->when($this->fromDate !== '', fn (Builder $query): Builder => $query->whereDate('consultation_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $query): Builder => $query->whereDate('consultation_date', '<=', $this->toDate))
            ->orderByDesc('consultation_date')->orderByDesc('medical_record_id')->paginate(10);
    }

    public function render(): View
    {
        Gate::authorize('queue-view');

        return view('livewire.medical-records-table', ['medicalRecords' => $this->medicalRecords]);
    }
}
