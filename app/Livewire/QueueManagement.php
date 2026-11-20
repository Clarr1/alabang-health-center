<?php

namespace App\Livewire;

use App\Models\Household;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Services\QueueWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class QueueManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public string $patientSearch = '';

    public string $status = '';

    public string $queueDate = '';

    #[Locked]
    public ?int $selectedPatientId = null;

    public function mount(): void
    {
        Gate::authorize('queue-view');
        $this->queueDate = now('Asia/Manila')->toDateString();
        $this->patientSearch = (string) request()->query('patient', '');
        $requestedStatus = request()->query('status', '');
        $this->status = in_array($requestedStatus, Queue::STATUSES, true) ? $requestedStatus : '';

        if (ctype_digit($this->patientSearch) && Gate::allows('queue-register')) {
            $this->selectPatient((int) $this->patientSearch);
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'queueDate'], true)) {
            $this->resetPage();
            $this->resetErrorBag($property);
        }
    }

    public function selectPatient(int $patientId): void
    {
        Gate::authorize('queue-register');
        PatientInformation::findOrFail($patientId);
        $this->selectedPatientId = $patientId;
        $this->reset('patientSearch');
        $this->resetErrorBag();
        unset($this->selectedPatient, $this->patientResults);
    }

    public function addPatient(): void
    {
        Gate::authorize('queue-register');
        $this->resetErrorBag();

        try {
            $workflow = app(QueueWorkflow::class);
            $this->validate($workflow->assignmentRules(), $workflow->assignmentMessages());
            $queue = $workflow->addPatient($this->selectedPatientId);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to add this patient to the queue. Please try again.');

            return;
        }

        $this->queueDate = $queue->queue_date->toDateString();
        $this->reset('selectedPatientId', 'patientSearch', 'search', 'status');
        $this->resetPage();
        unset($this->queues, $this->patientResults, $this->selectedPatient, $this->assignedNumbers);
        session()->flash('success', "Patient added successfully. Assigned queue number: {$queue->formattedQueueNumber()}.");
    }

    #[Computed]
    public function selectedPatient(): ?PatientInformation
    {
        return $this->selectedPatientId === null ? null : PatientInformation::find($this->selectedPatientId);
    }

    #[Computed]
    public function assignedNumbers(): Collection
    {
        Gate::authorize('queue-view');

        return Queue::whereDate('queue_date', now('Asia/Manila')->toDateString())
            ->orderBy('queue_number')->get(['queue_id', 'queue_number', 'status']);
    }

    public function changeStatus(int $queueId, string $expectedStatus, string $nextStatus): void
    {
        $this->resetErrorBag();
        Gate::authorize('queue-transition', $nextStatus);

        try {
            $queue = app(QueueWorkflow::class)->transition($queueId, $expectedStatus, $nextStatus);
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to update this visit. Refresh the queue and try again.');

            return;
        }

        unset($this->queues);
        session()->flash('success', "Queue #{$queue->formattedQueueNumber()}: {$queue->status}.");

        if ($nextStatus === 'Vital Signs') {
            $this->redirect(route('queues.vital-signs', ['patient_id' => $queue->patient_id, 'queue_id' => $queueId]), navigate: true);
        } elseif ($nextStatus === 'In Consultation') {
            $this->redirect(route('queues.consultation', ['patient_id' => $queue->patient_id, 'queue_id' => $queueId]), navigate: true);
        }
    }

    #[Computed]
    public function patientResults(): Collection
    {
        Gate::authorize('queue-view');
        $search = trim($this->patientSearch);

        if ($search === '') {
            return collect();
        }

        return PatientInformation::query()->with('household:id,family_number')->where(function (Builder $query) use ($search): void {
            $this->searchPatients($query, $search);
        })->orderBy('last_name')->limit(8)->get();
    }

    #[Computed]
    public function familyResults(): Collection
    {
        Gate::authorize('queue-view');
        $search = trim($this->patientSearch);

        if ($search === '') {
            return collect();
        }

        return Household::query()
            ->where('family_number', 'like', '%'.$search.'%')
            ->has('members')
            ->with(['members' => function ($query): void {
                $query->select([
                    'patient_id',
                    'household_id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'birthdate',
                    'sex',
                    'contact_number',
                    'household_relationship',
                ])->orderBy('last_name')->orderBy('first_name')->orderBy('patient_id');
            }])
            ->orderByRaw('CASE WHEN family_number = ? THEN 0 ELSE 1 END', [$search])
            ->orderBy('family_number')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function queues(): LengthAwarePaginator
    {
        Gate::authorize('queue-view');
        $validator = Validator::make(['queueDate' => $this->queueDate, 'status' => $this->status, 'search' => $this->search], [
            'queueDate' => ['required', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:'.implode(',', Queue::STATUSES)],
            'search' => ['string', 'max:255'],
        ]);
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return Queue::query()->whereRaw('1 = 0')->paginate(10);
        }
        $search = trim($this->search);

        return Queue::query()->with(['patient', 'medicalRecord'])
            ->whereDate('queue_date', $this->queueDate)
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('queue_number', 'like', "%{$search}%")
                        ->when(ctype_digit($search), fn (Builder $query): Builder => $query->orWhere('queue_number', ltrim($search, '0') ?: '0'))
                        ->orWhere('patient_id', 'like', "%{$search}%")
                        ->orWhereHas('patient', function (Builder $query) use ($search): void {
                            $this->searchPatients($query, $search);
                        });
                });
            })->orderBy('queue_number')->paginate(10);
    }

    private function searchPatients(Builder $query, string $search): void
    {
        foreach (preg_split('/\\s+/', trim($search)) as $term) {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('patient_id', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('middle_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%");
            });
        }
    }

    public function render(): View
    {
        return view('livewire.queue-management');
    }
}
