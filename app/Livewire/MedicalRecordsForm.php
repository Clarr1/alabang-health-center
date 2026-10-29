<?php

namespace App\Livewire;

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Services\QueueWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title("Doctor's Consultation")]
class MedicalRecordsForm extends Component
{
    #[Locked]
    public ?int $queueId = null;

    #[Locked]
    public $patient;

    #[Locked]
    public $patient_id;

    public $vital_signs_id = '';

    public $diagnosis = '';

    public $prescription = '';

    public $treatment = '';

    public $consultation_notes = '';

    public $consultation_date = '';

    public $follow_up_date = '';

    public $selectedMedicalRecordId = null;

    public $showMedicalRecordModal = false;

    public function mount(?int $patient_id = null, ?int $queue_id = null): void
    {
        Gate::authorize('queue-consult');
        $this->consultation_date = now('Asia/Manila')->toDateString();
        $this->patient_id = null;

        if ($queue_id !== null) {
            $queue = Queue::findOrFail($queue_id);
            abort_unless((int) $queue->patient_id === $patient_id, 404);
            abort_unless($queue->status === 'In Consultation' && $queue->queue_date->toDateString() === now('Asia/Manila')->toDateString(), 409);
            abort_unless($queue->consulting_doctor_id === null || (int) $queue->consulting_doctor_id === (int) Auth::id(), 403);
            $this->openConsultation(app(QueueWorkflow::class)->resumeConsultation($queue_id));
        }
    }

    #[Computed]
    public function currentQueue(): ?Queue
    {
        Gate::authorize('queue-consult');

        return $this->queueId === null ? null : Queue::where('patient_id', $this->patient_id)->find($this->queueId);
    }

    #[Computed]
    public function consultationQueue(): Collection
    {
        Gate::authorize('queue-consult');

        return Queue::with('patient')->whereDate('queue_date', now('Asia/Manila')->toDateString())
            ->where('status', 'Ready for Consultation')
            ->whereHas('vitalSign', fn (Builder $query): Builder => $query->whereColumn('vital_signs.patient_id', 'queues.patient_id'))
            ->doesntHave('medicalRecord')->orderBy('queue_number')->get();
    }

    #[Computed]
    public function activeConsultations(): Collection
    {
        Gate::authorize('queue-consult');

        return Queue::with('patient')->whereDate('queue_date', now('Asia/Manila')->toDateString())
            ->where('status', 'In Consultation')->where('consulting_doctor_id', Auth::id())
            ->orderBy('queue_number')->get();
    }

    public function startConsultation(int $queueId): void
    {
        Gate::authorize('queue-consult');
        if ($this->queueId !== null) {
            $this->addError('queue', 'Complete the selected consultation before starting another.');

            return;
        }

        try {
            $queue = app(QueueWorkflow::class)->transition($queueId, 'Ready for Consultation', 'In Consultation');
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to start this consultation. Please try again.');

            return;
        }

        $this->openConsultation($queue);
    }

    public function resumeConsultation(int $queueId): void
    {
        Gate::authorize('queue-consult');
        if ($this->queueId !== null) {
            $this->addError('queue', 'Complete the selected consultation first.');

            return;
        }

        try {
            $queue = app(QueueWorkflow::class)->resumeConsultation($queueId);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to open this consultation. Please try again.');

            return;
        }

        $this->openConsultation($queue);
    }

    private function openConsultation(Queue $queue): void
    {
        $this->resetForm();
        $this->patient = PatientInformation::findOrFail($queue->patient_id);
        $this->patient_id = $queue->patient_id;
        $this->queueId = $queue->queue_id;
        $this->vital_signs_id = $queue->vitalSign()->value('vital_id') ?? '';
        unset($this->vitalSigns, $this->medicalRecords, $this->consultationQueue, $this->activeConsultations);
        $this->dispatch('queue-updated');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'queueId' => ['required', 'integer', 'exists:queues,queue_id'],
            'patient_id' => ['required', 'integer', 'exists:patient_information,patient_id'],
            'vital_signs_id' => ['required', 'integer', 'exists:vital_signs,vital_id'],
            'diagnosis' => ['required', 'string'],
            'prescription' => ['nullable', 'string'],
            'treatment' => [$this->queueId !== null ? 'required' : 'nullable', 'string'],
            'consultation_notes' => [$this->queueId !== null ? 'required' : 'nullable', 'string'],
            'consultation_date' => ['required', 'date'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:consultation_date'],
        ];
    }

    public function selectVitalSign(int $vitalId): void
    {
        Gate::authorize('queue-consult');
        if ($this->queueId !== null && ! Queue::findOrFail($this->queueId)->vitalSign()->whereKey($vitalId)->exists()) {
            $this->addError('vital_signs_id', 'Use the vital signs recorded for this queue visit.');

            return;
        }
        if ($this->patient && $this->patient->vitalSigns()->whereKey($vitalId)->exists()) {
            $this->vital_signs_id = $vitalId;
        }
    }

    public function viewMedicalRecord(int $medicalRecordId): void
    {
        Gate::authorize('queue-consult');
        if (! $this->patient || ! $this->patient->medicalRecords()->whereKey($medicalRecordId)->exists()) {
            return;
        }

        $this->selectedMedicalRecordId = $medicalRecordId;
        $this->showMedicalRecordModal = true;
        unset($this->selectedMedicalRecord);
    }

    public function closeMedicalRecordModal(): void
    {
        $this->selectedMedicalRecordId = null;
        $this->showMedicalRecordModal = false;
        unset($this->selectedMedicalRecord);
    }

    #[Computed]
    public function vitalSigns(): Collection
    {
        Gate::authorize('queue-consult');

        return $this->patient
            ? $this->patient->vitalSigns()->when($this->queueId !== null, fn (Builder $query): Builder => $query->where('queue_id', $this->queueId))
                ->with('recordedBy')->latest('recorded_at')->latest('vital_id')->get()
            : collect();
    }

    #[Computed]
    public function medicalRecords(): Collection
    {
        Gate::authorize('queue-consult');

        return $this->patient
            ? $this->patient->medicalRecords()->with('handledBy', 'vitalSign')->latest('consultation_date')->latest('medical_record_id')->get()
            : collect();
    }

    #[Computed]
    public function selectedMedicalRecord(): ?MedicalRecord
    {
        Gate::authorize('queue-consult');
        if (! $this->patient || ! $this->selectedMedicalRecordId) {
            return null;
        }

        return $this->patient->medicalRecords()
            ->with(['patient', 'handledBy', 'vitalSign.recordedBy'])->find($this->selectedMedicalRecordId);
    }

    public function saveMedicalRecord(): void
    {
        Gate::authorize('queue-consult');
        $this->validate();
        if (! $this->patient || (int) $this->patient->patient_id !== (int) $this->patient_id || ! $this->patient->vitalSigns()->whereKey($this->vital_signs_id)->exists()) {
            $this->addError('vital_signs_id', 'Select a vital signs record for this consultation.');

            return;
        }

        $save = fn (Queue $queue): MedicalRecord => MedicalRecord::create([
            'queue_id' => $queue->queue_id,
            'patient_id' => $this->patient_id,
            'vital_signs_id' => $this->vital_signs_id,
            'handled_by' => Auth::id(),
            'diagnosis' => $this->diagnosis,
            'prescription' => $this->prescription ?: null,
            'treatment' => $this->treatment ?: null,
            'consultation_notes' => $this->consultation_notes ?: null,
            'consultation_date' => $this->consultation_date,
            'follow_up_date' => $this->follow_up_date ?: null,
            'created_at' => now(),
        ]);

        try {
            app(QueueWorkflow::class)->saveMedicalRecord($this->queueId, (int) $this->patient_id, (int) $this->vital_signs_id, $save);
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to save this visit. Check the current queue status and try again.');

            return;
        }

        $this->resetForm();
        $this->dispatch('medical-record-saved');
        $this->dispatch('queue-updated');
    }

    public function resetForm(): void
    {
        $this->reset([
            'queueId', 'patient', 'patient_id', 'vital_signs_id',
            'diagnosis', 'prescription', 'treatment', 'consultation_notes', 'follow_up_date',
        ]);
        $this->consultation_date = now('Asia/Manila')->toDateString();
        $this->patient_id = null;
        $this->closeMedicalRecordModal();
        $this->resetValidation();
        unset($this->medicalRecords, $this->vitalSigns, $this->consultationQueue, $this->activeConsultations);
    }

    public function render(): View
    {
        Gate::authorize('queue-consult');

        return view('livewire.medical-records-form');
    }
}
