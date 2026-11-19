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
        Gate::authorize('medical-records-view');
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
            unset($this->patients, $this->medicalRecords);
        }
    }

    public function clearFilters(): void
    {
        Gate::authorize('medical-records-view');
        $this->reset('search', 'fromDate', 'toDate');
        $this->resetPage();
        $this->resetValidation();
        unset($this->patients, $this->medicalRecords);
    }

    #[Computed]
    public function patients(): LengthAwarePaginator
    {
        Gate::authorize('medical-records-view');
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

        return PatientInformation::query()
            ->withCount(['medicalRecords' => function (Builder $query): void {
                $this->applyRecordFilters($query);
            }])
            ->withMax(['medicalRecords' => function (Builder $query): void {
                $this->applyRecordFilters($query);
            }], 'consultation_date')
            ->when($search !== '', function (Builder $query) use ($search): void {
                if (ctype_digit($search)) {
                    $query->whereKey($search);

                    return;
                }

                $query->where(function (Builder $query) use ($search): void {
                    foreach (preg_split('/\\s+/', $search) as $term) {
                        $query->where(function (Builder $query) use ($term): void {
                            $query->where('first_name', 'like', "%{$term}%")
                                ->orWhere('middle_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                });
            })
            ->whereHas('medicalRecords', function (Builder $query): void {
                $this->applyRecordFilters($query);
            })
            ->orderByDesc('medical_records_max_consultation_date')
            ->orderByDesc('patient_id')
            ->paginate(10);
    }

    #[Computed]
    public function medicalRecords(): LengthAwarePaginator
    {
        return $this->patients();
    }

    private function applyRecordFilters(Builder $query): void
    {
        $query
            ->when($this->fromDate !== '', fn (Builder $query): Builder => $query->whereDate('consultation_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $query): Builder => $query->whereDate('consultation_date', '<=', $this->toDate));
    }

    public function render(): View
    {
        Gate::authorize('medical-records-view');

        return view('livewire.medical-records-table', ['patients' => $this->patients]);
    }
}
