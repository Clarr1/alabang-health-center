<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\VitalSign;
use App\Services\QueueWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class VitalSignsForm extends Component
{
    #[Locked]
    public ?int $queueId = null;

    public $patient;

    public $patient_id;

    public $patientSearch = '';

    public $blood_pressure = '';

    public $temperature = '';

    public $pulse_rate = '';

    public $respiratory_rate = '';

    public $height = '';

    public $weight = '';

    public $bmi = '';

    public $recorded_at = '';

    public function mount(?int $patient_id = null, ?int $queue_id = null): void
    {
        if ($patient_id !== null) {
            $this->selectPatient($patient_id);
        }

        $this->recorded_at = now('Asia/Manila')->toDateString();

        if ($queue_id !== null) {
            Gate::authorize('queue-register');
            $queue = Queue::findOrFail($queue_id);
            abort_unless((int) $queue->patient_id === (int) $this->patient_id, 404);
            abort_unless($queue->status === 'Vital Signs', 409);
            $this->queueId = $queue_id;
        }
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patient_information,patient_id'],
            'blood_pressure' => ['nullable', 'string', 'max:20'],
            'temperature' => ['nullable', 'numeric', 'min:25', 'max:45'],
            'pulse_rate' => ['nullable', 'integer', 'min:0', 'max:220'],
            'respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:80'],
            'height' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'weight' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'bmi' => ['nullable', 'numeric', 'min:5', 'max:100'],
            'recorded_at' => ['required', 'date'],
        ];
    }

    public function updatedHeight(): void
    {
        $this->calculateBmi();
    }

    public function updatedWeight(): void
    {
        $this->calculateBmi();
    }

    public function getBmiCategoryProperty(): string
    {
        if ($this->bmi === '' || $this->bmi === null) {
            return '---';
        }

        $bmi = (float) $this->bmi;

        if ($bmi < 18.5) {
            return 'Underweight';
        }

        if ($bmi < 25) {
            return 'Normal';
        }

        if ($bmi < 30) {
            return 'Overweight';
        }

        return 'Obese';
    }

    public function selectPatient(int $patientId): void
    {
        if ($this->queueId !== null) {
            $queue = Queue::findOrFail($this->queueId);
            abort_unless((int) $queue->patient_id === $patientId, 403);
        }

        $this->patient = PatientInformation::findOrFail($patientId);
        $this->patient_id = $this->patient->patient_id;
        $this->patientSearch = '';
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

        $this->calculateBmi();

        $save = fn (?Queue $queue = null): VitalSign => VitalSign::create([
            'queue_id' => $queue?->queue_id,
            'patient_id' => $this->patient_id,
            'recorded_by' => Auth::id(),
            'blood_pressure' => $this->blood_pressure ?: null,
            'temperature' => $this->temperature !== '' ? $this->temperature : null,
            'pulse_rate' => $this->pulse_rate !== '' ? (int) $this->pulse_rate : null,
            'respiratory_rate' => $this->respiratory_rate !== '' ? (int) $this->respiratory_rate : null,
            'height' => $this->height !== '' ? $this->height : null,
            'weight' => $this->weight !== '' ? $this->weight : null,
            'bmi' => $this->bmi !== '' ? $this->bmi : null,
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
        if ($this->queueId !== null) {
            $this->redirect(route('queues.index'), navigate: true);

            return;
        }
        $this->dispatch('vital-signs-saved');
    }

    public function render(): View
    {
        return view('livewire.vital-signs-form');
    }

    private function calculateBmi(): void
    {
        if ($this->height !== '' && $this->weight !== '' && (float) $this->height > 0) {
            $heightInMeters = (float) $this->height / 100;
            $this->bmi = round((float) $this->weight / ($heightInMeters * $heightInMeters), 1);

            return;
        }

        $this->bmi = '';
    }

    public function resetForm(): void
    {
        $patientId = $this->patient_id;
        $patient = $this->patient;

        $this->reset([
            'blood_pressure',
            'temperature',
            'pulse_rate',
            'respiratory_rate',
            'height',
            'weight',
            'bmi',
            'patientSearch',
        ]);

        $this->patient_id = $patientId;
        $this->patient = $patient;
        $this->recorded_at = now('Asia/Manila')->toDateString();

        if ($queue_id !== null) {
            Gate::authorize('queue-register');
            $queue = Queue::findOrFail($queue_id);
            abort_unless((int) $queue->patient_id === (int) $this->patient_id, 404);
            abort_unless($queue->status === 'Vital Signs', 409);
            $this->queueId = $queue_id;
        }
    }
}
