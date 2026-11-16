<?php

namespace App\Livewire;

use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\Symptom;
use App\Services\IndividualTreatmentRecord;
use App\Services\QueueWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    public string $diagnosisSelection = 'other';

    #[Locked]
    public ?int $catalogDiagnosisId = null;

    #[Locked]
    public string $catalogDiagnosisWording = '';

    public string $diagnosisSearch = '';

    public string $symptomSearch = '';

    public string $chief_complaint = '';

    #[Locked]
    public int $consultationVersion = 0;

    /** @var array<string, string> */
    public array $clinicalNotes = [];

    /** @var array<string, array{findings?: array<int, string>, other?: string}> */
    public array $physical_examination = [];

    public string $laboratory_requests = '';

    /** @var array<int, int> */
    public array $selectedSymptomIds = [];

    /** @var array<int, string> */
    public array $customSymptoms = [];

    public string $customSymptom = '';

    public $prescription = '';

    public $treatment = '';

    public $consultation_notes = '';

    public $consultation_date = '';

    public $follow_up_date = '';

    public $selectedMedicalRecordId = null;

    public $showMedicalRecordModal = false;

    #[Computed]
    public function availableSymptoms(): Collection
    {
        Gate::authorize('queue-consult');

        return Symptom::where('is_active', true)->where('name', 'like', '%'.mb_substr($this->symptomSearch, 0, 255).'%')
            ->whereNotIn('id', $this->selectedSymptomKeys())->orderBy('name')->limit(30)->get();
    }

    #[Computed]
    public function selectedSymptoms(): Collection
    {
        Gate::authorize('queue-consult');

        return Symptom::whereIn('id', $this->selectedSymptomKeys())->orderBy('name')->get();
    }

    /** @return array<int, int> */
    private function selectedSymptomKeys(): array
    {
        return array_map(intval(...), array_values(array_filter($this->selectedSymptomIds, fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))));
    }

    #[Computed]
    public function availableDiagnoses(): Collection
    {
        Gate::authorize('queue-consult');

        return Diagnosis::where('is_active', true)->where('review_status', 'approved')
            ->where('name', 'like', '%'.mb_substr($this->diagnosisSearch, 0, 255).'%')->orderBy('name')->limit(30)->get();
    }

    public function selectSymptom(int $symptomId): void
    {
        Gate::authorize('queue-consult');
        $symptom = Symptom::where('is_active', true)->findOrFail($symptomId);
        if (! in_array($symptomId, $this->selectedSymptomKeys(), true)) {
            $this->selectedSymptomIds[] = $symptomId;
        }
        $this->customSymptoms = array_values(array_filter($this->customSymptoms, fn (mixed $name): bool => ! is_string($name) || Diagnosis::normalizeName($name) !== Diagnosis::normalizeName($symptom->name)));
        unset($this->selectedSymptoms, $this->availableSymptoms);
    }

    public function removeSymptom(int $symptomId): void
    {
        Gate::authorize('queue-consult');
        $this->selectedSymptomIds = array_values(array_diff($this->selectedSymptomKeys(), [$symptomId]));
        unset($this->selectedSymptoms, $this->availableSymptoms);
    }

    public function addCustomSymptom(): void
    {
        Gate::authorize('queue-consult');
        $this->validate([
            'customSymptom' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'customSymptoms' => ['array', 'max:49'],
            'customSymptoms.*' => ['required', 'string', 'max:255'],
        ]);
        $names = $this->selectedSymptoms->pluck('name')->merge($this->customSymptoms)->map(fn (string $name): string => Diagnosis::normalizeName($name));
        if ($names->contains(Diagnosis::normalizeName($this->customSymptom))) {
            $this->addError('customSymptom', 'This symptom is already selected.');

            return;
        }
        $this->customSymptoms[] = $this->customSymptom;
        $this->customSymptom = '';
    }

    public function removeCustomSymptom(int $index): void
    {
        Gate::authorize('queue-consult');
        unset($this->customSymptoms[$index]);
        $this->customSymptoms = array_values($this->customSymptoms);
    }

    public function selectDiagnosis(string $selection): void
    {
        Gate::authorize('queue-consult');
        $diagnosis = $selection === 'other' ? null : Diagnosis::where('is_active', true)->where('review_status', 'approved')->findOrFail($selection);
        $this->diagnosisSelection = $selection;
        $this->catalogDiagnosisId = $diagnosis?->id;
        $this->catalogDiagnosisWording = $diagnosis?->name ?? '';
        $this->diagnosis = $diagnosis?->name ?? '';
        $this->diagnosisSearch = '';
    }

    public function mount(?int $patient_id = null, ?int $queue_id = null): void
    {
        Gate::authorize('queue-consult');
        $this->physical_examination = IndividualTreatmentRecord::emptyExamination();
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
        $this->consultation_date = $queue->queue_date->toDateString();
        $this->consultationVersion = $queue->consultation_version;
        if ($queue->consultation_draft !== null) {
            $this->fill($queue->consultation_draft);
        }
        $this->physical_examination = array_replace_recursive(IndividualTreatmentRecord::emptyExamination(), $this->physical_examination);
        unset($this->vitalSigns, $this->medicalRecords, $this->consultationQueue, $this->activeConsultations);
        $this->dispatch('queue-updated');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $clinicalRules = [
            'clinicalNotes' => ['array:'.implode(',', array_keys(IndividualTreatmentRecord::NOTES))],
            'physical_examination' => ['array:'.implode(',', array_keys(IndividualTreatmentRecord::EXAMINATIONS))],
        ];
        foreach (IndividualTreatmentRecord::NOTES as $field => $label) {
            $clinicalRules['clinicalNotes.'.$field] = ['nullable', 'string', 'max:10000'];
        }
        foreach (IndividualTreatmentRecord::EXAMINATIONS as $group => $findings) {
            $clinicalRules['physical_examination.'.$group] = ['array:findings,other'];
            $clinicalRules['physical_examination.'.$group.'.findings'] = ['array', 'max:'.count($findings)];
            $clinicalRules['physical_examination.'.$group.'.findings.*'] = ['string', 'distinct', Rule::in($findings)];
            $clinicalRules['physical_examination.'.$group.'.other'] = ['nullable', 'string', 'max:10000'];
        }

        return [
            ...$clinicalRules,
            'queueId' => ['required', 'integer', 'exists:queues,queue_id'],
            'patient_id' => ['required', 'integer', 'exists:patient_information,patient_id'],
            'vital_signs_id' => ['required', 'integer', 'exists:vital_signs,vital_id'],
            'chief_complaint' => ['nullable', 'string', 'max:10000'],
            'laboratory_requests' => ['nullable', 'string', 'max:10000'],
            'diagnosisSelection' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== 'other' && (! ctype_digit($value) || (int) $value !== $this->catalogDiagnosisId || ! Diagnosis::where('is_active', true)->where('review_status', 'approved')->whereKey($value)->exists())) {
                    $fail('Select an active, approved diagnosis or choose Other / New Diagnosis.');
                }
            }],
            'diagnosis' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'],
            'selectedSymptomIds' => ['array', 'max:50'],
            'selectedSymptomIds.*' => ['bail', 'integer', 'distinct', Rule::exists('symptoms', 'id')->where('is_active', true)],
            'customSymptoms' => ['array', 'max:50'],
            'customSymptoms.*' => ['bail', 'required', 'string', 'max:255', 'distinct:ignore_case', 'not_regex:/^\s*$/u'],
            'customSymptom' => ['nullable', 'string', 'max:255'],
            'prescription' => ['nullable', 'string', 'max:10000'],
            'treatment' => [$this->queueId !== null ? 'required' : 'nullable', 'string', 'max:10000'],
            'consultation_notes' => ['nullable', 'string', 'max:10000'],
            'consultation_date' => ['required', 'date', Rule::in([$this->currentQueue?->queue_date->toDateString() ?? $this->consultation_date])],
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
            ? $this->patient->medicalRecords()->with('handledBy', 'vitalSign', 'symptoms')->latest('consultation_date')->orderByRaw('COALESCE(encounter_at, created_at) DESC')->latest('medical_record_id')->get()
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
            ->with(['patient', 'handledBy', 'vitalSign.recordedBy', 'symptoms'])->find($this->selectedMedicalRecordId);
    }

    public function saveMedicalRecord(): void
    {
        Gate::authorize('queue-consult');
        $this->validate();
        if (trim($this->customSymptom) !== '') {
            $this->addError('customSymptom', 'Add this symptom using the Add button before saving.');

            return;
        }
        $symptomNames = $this->selectedSymptoms->pluck('name')->merge($this->customSymptoms)->map(fn (string $name): string => Diagnosis::normalizeName($name));
        if ($symptomNames->unique()->count() !== $symptomNames->count()) {
            $this->addError('customSymptoms', 'Each symptom may only be entered once.');

            return;
        }
        if (! $this->patient || (int) $this->patient->patient_id !== (int) $this->patient_id || ! $this->patient->vitalSigns()->whereKey($this->vital_signs_id)->exists()) {
            $this->addError('vital_signs_id', 'Select a vital signs record for this consultation.');

            return;
        }

        $save = function (Queue $queue): MedicalRecord {
            if ($this->diagnosisSelection === 'other') {
                $diagnosis = Diagnosis::firstOrCreate(['normalized_name' => Diagnosis::normalizeName($this->diagnosis)], [
                    'name' => $this->diagnosis, 'review_status' => 'pending', 'is_active' => false,
                ]);
                $diagnosisText = $this->diagnosis;
            } else {
                $diagnosis = Diagnosis::where('is_active', true)->where('review_status', 'approved')->lockForUpdate()->find($this->diagnosisSelection);
                if (! $diagnosis) {
                    throw ValidationException::withMessages(['diagnosisSelection' => 'This diagnosis is no longer available. Select another diagnosis.']);
                }
                $diagnosisText = $this->catalogDiagnosisWording;
            }
            $symptoms = Symptom::whereIn('id', $this->selectedSymptomIds)->where('is_active', true)->lockForUpdate()->get();
            if ($symptoms->count() !== count($this->selectedSymptomIds)) {
                throw ValidationException::withMessages(['selectedSymptomIds' => 'A selected symptom is no longer available. Review the selection.']);
            }
            $record = MedicalRecord::create([
                ...$this->clinicalValues(),
                'queue_id' => $queue->queue_id,
                'patient_id' => $this->patient_id,
                'vital_signs_id' => $this->vital_signs_id,
                'handled_by' => Auth::id(),
                'diagnosis' => $diagnosisText,
                'diagnosis_id' => $diagnosis->id,
                'chief_complaint' => $this->chief_complaint !== '' ? $this->chief_complaint : null,
                'custom_symptoms' => $this->customSymptoms ?: null,
                'laboratory_requests' => $this->laboratory_requests !== '' ? $this->laboratory_requests : null,
                'prescription' => $this->prescription ?: null,
                'treatment' => $this->treatment ?: null,
                'consultation_notes' => $this->consultation_notes ?: null,
                'consultation_date' => $this->consultation_date,
                'follow_up_date' => $this->follow_up_date ?: null,
                'created_at' => now(),
            ]);
            $record->symptoms()->attach($symptoms->mapWithKeys(fn (Symptom $symptom): array => [$symptom->id => ['symptom_name' => $symptom->name]])->all());

            return $record;
        };

        try {
            app(QueueWorkflow::class)->saveMedicalRecord($this->queueId, (int) $this->patient_id, (int) $this->vital_signs_id, $save, $this->consultationVersion);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to save this visit. Check the current queue status and try again.');

            return;
        }

        $this->resetForm();
        $this->dispatch('medical-record-saved');
        $this->dispatch('queue-updated');
    }

    /** @return array<string, mixed> */
    private function clinicalValues(): array
    {
        $values = [];
        foreach (IndividualTreatmentRecord::NOTES as $field => $label) {
            $values[$field] = ($this->clinicalNotes[$field] ?? '') !== '' ? $this->clinicalNotes[$field] : null;
        }

        $examination = array_filter($this->physical_examination, fn (array $group): bool => filled($group['findings'] ?? []) || filled($group['other'] ?? ''));

        return [...$values, 'physical_examination' => $examination ?: null];
    }

    public function saveDraft(): void
    {
        Gate::authorize('queue-consult');
        $rules = $this->rules();
        foreach (['diagnosis', 'treatment'] as $field) {
            $rules[$field] = array_map(fn (mixed $rule): mixed => $rule === 'required' ? 'nullable' : $rule, $rules[$field]);
        }
        $this->validate($rules);
        $fields = ['chief_complaint', 'laboratory_requests', 'diagnosisSelection', 'catalogDiagnosisId', 'catalogDiagnosisWording', 'diagnosis', 'selectedSymptomIds', 'customSymptoms', 'customSymptom', 'prescription', 'treatment', 'consultation_notes', 'follow_up_date', 'clinicalNotes', 'physical_examination'];
        try {
            $queue = app(QueueWorkflow::class)->saveConsultationDraft($this->queueId, (int) $this->patient_id, (int) $this->vital_signs_id, $this->consultationVersion, $this->only($fields));
            $this->consultationVersion = $queue->consultation_version;
            unset($this->currentQueue);
            session()->flash('success', 'Draft saved. The visit remains in consultation.');
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
        } catch (QueryException $exception) {
            report($exception);
            $this->addError('queue', 'Unable to save the draft. Your entries have been retained.');
        }
    }

    public function resetForm(): void
    {
        $this->reset([
            'queueId', 'patient', 'patient_id', 'vital_signs_id',
            'diagnosis', 'prescription', 'treatment', 'consultation_notes', 'follow_up_date',
            'chief_complaint', 'laboratory_requests', 'diagnosisSelection', 'diagnosisSearch', 'symptomSearch', 'selectedSymptomIds', 'customSymptoms', 'customSymptom',
            'catalogDiagnosisId', 'catalogDiagnosisWording', 'clinicalNotes', 'physical_examination', 'consultationVersion',
        ]);
        $this->consultation_date = now('Asia/Manila')->toDateString();
        $this->patient_id = null;
        $this->physical_examination = IndividualTreatmentRecord::emptyExamination();
        $this->closeMedicalRecordModal();
        $this->resetValidation();
        unset($this->medicalRecords, $this->vitalSigns, $this->consultationQueue, $this->activeConsultations);
        unset($this->selectedSymptoms, $this->availableSymptoms, $this->availableDiagnoses);
    }

    public function render(): View
    {
        Gate::authorize('queue-consult');

        return view('livewire.medical-records-form');
    }
}
