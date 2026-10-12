<?php

namespace App\Livewire;

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

    public $queueNumber = '';

    #[Locked]
    public ?int $selectedPatientId = null;

    #[Locked]
    public ?int $pendingPatientId = null;

    #[Locked]
    public ?int $pendingQueueNumber = null;

    #[Locked]
    public string $assignmentDate = '';

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
        $this->reset('patientSearch', 'queueNumber', 'pendingPatientId', 'pendingQueueNumber', 'assignmentDate');
        $this->resetErrorBag();
        unset($this->selectedPatient, $this->patientResults, $this->pendingPatient);
    }

    public function prepareAssignment(): void
    {
        Gate::authorize('queue-register');
        $workflow = app(QueueWorkflow::class);
        $date = now('Asia/Manila')->toDateString();
        $this->reset('pendingPatientId', 'pendingQueueNumber', 'assignmentDate');
        $this->resetErrorBag();
        $validated = $this->validate($workflow->assignmentRules($date), $workflow->assignmentMessages());

        if (Queue::whereDate('queue_date', $date)->where('patient_id', $this->selectedPatientId)->whereNotIn('status', Queue::TERMINAL_STATUSES)->exists()) {
            $this->addError('queue', 'This patient already has an active queue entry today.');

            return;
        }

        $this->pendingPatientId = (int) $validated['selectedPatientId'];
        $this->pendingQueueNumber = (int) $validated['queueNumber'];
        $this->assignmentDate = $date;
        unset($this->pendingPatient);
        $this->dispatch('queue-assignment-prepared');
    }

    public function addPatient(): void
    {
        Gate::authorize('queue-register');
        $this->resetErrorBag();

        try {
            if ($this->pendingPatientId === null || $this->pendingQueueNumber === null) {
                throw ValidationException::withMessages(['queue' => 'Review the patient and assigned number before confirming.']);
            }

            $queue = app(QueueWorkflow::class)->addPatient($this->pendingPatientId, $this->pendingQueueNumber, $this->assignmentDate);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
            $this->dispatch('queue-assignment-finished');
            $this->reset('pendingPatientId', 'pendingQueueNumber', 'assignmentDate');

            return;
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to save this assigned number. Refresh the queue and try again.');
            $this->dispatch('queue-assignment-finished');
            $this->reset('pendingPatientId', 'pendingQueueNumber', 'assignmentDate');

            return;
        }

        $this->queueDate = now('Asia/Manila')->toDateString();
        $this->reset('selectedPatientId', 'queueNumber', 'pendingPatientId', 'pendingQueueNumber', 'assignmentDate', 'patientSearch', 'search', 'status');
        $this->resetPage();
        unset($this->queues, $this->patientResults, $this->selectedPatient, $this->pendingPatient, $this->assignedNumbers);
        session()->flash('success', "Patient added successfully. Assigned queue number: {$queue->queue_number}.");
        $this->dispatch('queue-assignment-finished');
    }

    #[Computed]
    public function selectedPatient(): ?PatientInformation
    {
        return $this->selectedPatientId === null ? null : PatientInformation::find($this->selectedPatientId);
    }

    #[Computed]
    public function pendingPatient(): ?PatientInformation
    {
        return $this->pendingPatientId === null ? null : PatientInformation::find($this->pendingPatientId);
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
        session()->flash('success', "Queue #{$queue->queue_number}: {$queue->status}.");

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

        return PatientInformation::query()->where(function (Builder $query) use ($search): void {
            $this->searchPatients($query, $search);
        })->orderBy('last_name')->limit(8)->get();
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

        return Queue::query()->with('patient')
            ->whereDate('queue_date', $this->queueDate)
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('status', $this->status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('queue_number', 'like', "%{$search}%")
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
