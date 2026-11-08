<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\VitalSign;
use App\Services\QueueWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class VitalSignsForm extends Component
{
    use VitalSignFields;

    #[Locked]
    public ?int $queueId = null;

    public $patient;

    public $patient_id;

    public $patientSearch = '';

    public function mount(?int $patient_id = null, ?int $queue_id = null): void
    {
        Gate::authorize('queue-register');
        if ($patient_id !== null) {
            $this->selectPatient($patient_id);
        }

        $this->recorded_at = now('Asia/Manila')->toDateString();

        if ($queue_id !== null) {
            Gate::authorize('queue-register');
            $queue = Queue::findOrFail($queue_id);
            abort_unless((int) $queue->patient_id === (int) $this->patient_id, 404);
            abort_unless($queue->status === 'Vital Signs' && $queue->queue_date->toDateString() === now('Asia/Manila')->toDateString(), 409);
            $this->queueId = $queue_id;
        }
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patient_information,patient_id'],
            ...$this->vitalSignRules(),
        ];
    }

    public function selectPatient(int $patientId): void
    {
        Gate::authorize('queue-register');
        if ($this->queueId !== null) {
            $queue = Queue::findOrFail($this->queueId);
            abort_unless((int) $queue->patient_id === $patientId, 403);
        }

        $this->patient = PatientInformation::findOrFail($patientId);
        $this->patient_id = $this->patient->patient_id;
        $this->patientSearch = '';
    }

    public function getCurrentQueueProperty(): ?Queue
    {
        Gate::authorize('queue-register');

        return $this->queueId === null ? null : Queue::where('patient_id', $this->patient_id)->find($this->queueId);
    }

    public function getVitalSignsQueueProperty(): Collection
    {
        Gate::authorize('queue-register');

        return Queue::with('patient')->whereDate('queue_date', now('Asia/Manila')->toDateString())
            ->whereIn('status', ['Waiting', 'Called', 'Vital Signs'])->orderBy('queue_number')->get();
    }

    public function selectQueue(int $queueId, string $expectedStatus): void
    {
        Gate::authorize('queue-register');
        $queue = DB::transaction(function () use ($queueId, $expectedStatus): Queue {
            $queue = Queue::query()->lockForUpdate()->findOrFail($queueId);
            if ($queue->queue_date->toDateString() !== now('Asia/Manila')->toDateString()
                || $queue->status !== $expectedStatus
                || ! in_array($queue->status, ['Waiting', 'Called', 'Vital Signs'], true)) {
                throw ValidationException::withMessages(['queue' => 'This queue entry has changed. Check the current queue and select the patient again.']);
            }
            $workflow = app(QueueWorkflow::class);
            if ($queue->status === 'Waiting') {
                $queue = $workflow->transition($queueId, 'Waiting', 'Called');
            }
            if ($queue->status === 'Called') {
                $queue = $workflow->transition($queueId, 'Called', 'Vital Signs');
            }

            return $queue;
        }, 5);
        $this->resetForm();
        $this->queueId = $queue->queue_id;
        $this->selectPatient($queue->patient_id);
        $this->dispatch('vital-signs-patient-selected');
    }

    public function getPatientResultsProperty()
    {
        $search = trim($this->patientSearch);

        if (mb_strlen($search) < 2) {
            return collect();
        }

        return PatientInformation::query()
            ->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_id', 'like', "%{$search}%");
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(8)
            ->get();
    }

    public function saveVitalSigns(): void
    {
        Gate::authorize('queue-register');
        $this->calculateBmi();
        $this->validate();
        $activeQueue = Queue::where('patient_id', $this->patient_id)
            ->whereDate('queue_date', now('Asia/Manila')->toDateString())
            ->whereNotIn('status', Queue::TERMINAL_STATUSES)->first();

        if ($this->queueId === null && $activeQueue !== null) {
            $this->queueId = $activeQueue->queue_id;
        }
        if (! $this->patient || (int) $this->patient->patient_id !== (int) $this->patient_id) {
            $this->addError('patient_id', 'Select the patient for this vital signs record.');

            return;
        }

        $save = fn (?Queue $queue = null): VitalSign => VitalSign::create([
            'queue_id' => $queue?->queue_id,
            'patient_id' => $this->patient_id,
            'recorded_by' => Auth::id(),
            ...$this->vitalSignValues(),
            'recorded_at' => $queue !== null ? now() : ($this->recorded_at ?: now()),
        ]);

        try {
            if ($this->queueId !== null) {
                app(QueueWorkflow::class)->saveVitalSigns(
                    $this->queueId,
                    (int) $this->patient_id,
                    $save,
                );
            } else {
                $save();
            }
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to save this visit. Check the current queue status and try again.');

            return;
        }

        session()->flash('success', $this->queueId !== null ? 'Vital signs saved successfully. The visit is now ready for consultation.' : 'Vital signs saved successfully.');
        $this->resetForm();
        $this->dispatch('vital-signs-saved');
    }

    public function render(): View
    {
        return view('livewire.vital-signs-form');
    }

    public function resetForm(): void
    {
        Gate::authorize('queue-register');
        $this->reset([
            'queueId', 'patient_id', 'patient', 'blood_pressure', 'temperature',
            'pulse_rate', 'respiratory_rate', 'height', 'weight', 'bmi', 'patientSearch',
        ]);
        $this->resetErrorBag();
        $this->recorded_at = now('Asia/Manila')->toDateString();
    }
}
