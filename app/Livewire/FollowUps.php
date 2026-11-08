<?php

namespace App\Livewire;

use App\Models\FollowUp;
use App\Models\FollowUpMessage;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Services\FollowUpWorkflow;
use App\Services\TextBee;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class FollowUps extends Component
{
    use WithPagination;

    public string $search = '';

    public string $from = '';

    public string $to = '';

    public string $status = '';

    public string $patientSearch = '';

    public string $patient_id = '';

    public string $medical_record_id = '';

    public string $date = '';

    public string $time = '09:00';

    public string $purpose = '';

    public string $internal_notes = '';

    public string $mobile = '';

    public bool $sms_consent = false;

    #[Locked]
    public ?int $appointmentId = null;

    #[Locked]
    public ?int $version = null;

    public function mount(): void
    {
        Gate::authorize('follow-ups-manage');
        $this->date = now('Asia/Manila')->addDay()->toDateString();
        if (request()->integer('consultation')) {
            $record = MedicalRecord::findOrFail(request()->integer('consultation'));
            $existing = FollowUp::where('medical_record_id', $record->medical_record_id)->first();
            if ($existing) {
                $this->edit($existing->id);
            } else {
                $this->patient_id = (string) $record->patient_id;
                $this->updatedPatientId();
                $this->medical_record_id = (string) $record->medical_record_id;
                $this->updatedMedicalRecordId();
            }
        }
    }

    public function updated(string $property): void
    {
        Gate::authorize('follow-ups-manage');
        if (in_array($property, ['search', 'from', 'to', 'status'], true)) {
            $this->resetPage();
            $this->resetErrorBag(['search', 'from', 'to', 'status']);
        }
    }

    public function updatedPatientId(): void
    {
        Gate::authorize('follow-ups-manage');
        if ($this->appointmentId) {
            return;
        }
        $patient = PatientInformation::find($this->patient_id);
        $this->mobile = $patient?->contact_number ?? '';
        $this->sms_consent = (bool) $patient?->sms_consent;
        $this->medical_record_id = '';
        if ($patient) {
            $record = $patient->medicalRecords()->whereNotNull('follow_up_date')
                ->whereNotIn('medical_record_id', FollowUp::whereNotNull('medical_record_id')->select('medical_record_id'))
                ->latest('follow_up_date')->first();
            if ($record) {
                $this->medical_record_id = (string) $record->medical_record_id;
                $this->updatedMedicalRecordId();
            }
        }
    }

    public function updatedMedicalRecordId(): void
    {
        Gate::authorize('follow-ups-manage');
        if ($this->appointmentId || ! $this->medical_record_id) {
            return;
        }
        $record = MedicalRecord::where('patient_id', $this->patient_id)->findOrFail($this->medical_record_id);
        if ($record->follow_up_date) {
            $this->date = $record->follow_up_date->format('Y-m-d');
        }
    }

    public function newAppointment(): void
    {
        Gate::authorize('follow-ups-manage');
        $this->reset('appointmentId', 'version', 'patient_id', 'medical_record_id', 'purpose', 'internal_notes', 'mobile', 'sms_consent', 'patientSearch');
        $this->date = now('Asia/Manila')->addDay()->toDateString();
        $this->time = '09:00';
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        Gate::authorize('follow-ups-manage');
        $appointment = FollowUp::with('patient')->findOrFail($id);
        $this->appointmentId = $id;
        $this->version = $appointment->version;
        $this->patient_id = (string) $appointment->patient_id;
        $this->medical_record_id = (string) ($appointment->medical_record_id ?? '');
        $this->date = $appointment->scheduled_at->setTimezone('Asia/Manila')->format('Y-m-d');
        $this->time = $appointment->scheduled_at->setTimezone('Asia/Manila')->format('H:i');
        $this->purpose = $appointment->purpose;
        $this->internal_notes = $appointment->internal_notes ?? '';
        $this->mobile = $appointment->mobile;
        $this->sms_consent = (bool) $appointment->patient->sms_consent;
        $this->resetValidation();
    }

    public function save(FollowUpWorkflow $workflow): void
    {
        Gate::authorize('follow-ups-manage');
        $appointment = $workflow->save($this->only(['patient_id', 'medical_record_id', 'date', 'time', 'purpose', 'internal_notes', 'mobile', 'sms_consent']), $this->appointmentId, $this->version);
        $this->edit($appointment->id);
        session()->flash('follow-up-status', 'Appointment saved. Consented notifications are pending the SMS worker.');
    }

    public function transition(int $id, int $version, string $status, FollowUpWorkflow $workflow): void
    {
        $workflow->transition($id, $version, $status);
        if ($id === $this->appointmentId) {
            $this->edit($id);
        }
        session()->flash('follow-up-status', 'Appointment marked '.$status.'.');
    }

    public function remind(int $id, int $version, FollowUpWorkflow $workflow): void
    {
        $workflow->remind($id, $version);
        session()->flash('follow-up-status', 'Reminder requested. It will be processed by the SMS worker.');
    }

    public function retry(int $id, FollowUpWorkflow $workflow): void
    {
        $workflow->retry($id);
        session()->flash('follow-up-status', 'Retry queued. Appointment and consent will be checked again.');
    }

    public function clearFilters(): void
    {
        Gate::authorize('follow-ups-manage');
        $this->reset('search', 'from', 'to', 'status');
        $this->resetPage();
        $this->resetErrorBag(['search', 'from', 'to', 'status']);
    }

    public function render(): View
    {
        Gate::authorize('follow-ups-manage');
        $validator = Validator::make($this->only(['search', 'from', 'to', 'status']), [
            'search' => ['string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->from !== '' ? ['after_or_equal:from'] : [])],
            'status' => ['nullable', 'in:Scheduled,Completed,Cancelled,No Show'],
        ]);
        $validFilters = $validator->passes();
        if (! $validFilters) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->resetErrorBag($field);
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
        $appointments = FollowUp::with(['patient', 'messages' => fn ($query) => $query->latest('id')])
            ->when(! $validFilters, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($this->search !== '', function ($query): void {
                $query->whereHas('patient', function ($query): void {
                    $query->where('patient_id', 'like', '%'.$this->search.'%')->orWhere(function ($query): void {
                        foreach (preg_split('/\s+/', trim($this->search), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                            $query->where(fn ($query) => $query->where('first_name', 'like', '%'.$part.'%')->orWhere('middle_name', 'like', '%'.$part.'%')->orWhere('last_name', 'like', '%'.$part.'%'));
                        }
                    });
                });
            })
            ->when(in_array($this->status, ['Scheduled', 'Completed', 'Cancelled', 'No Show'], true), fn ($query) => $query->where('status', $this->status))
            ->when($validFilters && $this->from !== '', fn ($query) => $query->where('scheduled_at', '>=', CarbonImmutable::parse($this->from, 'Asia/Manila')->startOfDay()->utc()))
            ->when($validFilters && $this->to !== '', fn ($query) => $query->where('scheduled_at', '<', CarbonImmutable::parse($this->to, 'Asia/Manila')->addDay()->startOfDay()->utc()))
            ->orderBy('scheduled_at')->orderBy('id')->paginate(8);
        $patients = PatientInformation::where(function ($query): void {
            $query->where('first_name', 'like', '%'.$this->patientSearch.'%')->orWhere('last_name', 'like', '%'.$this->patientSearch.'%')->orWhere('patient_id', $this->patientSearch);
        })->orderBy('last_name')->limit(50)->get();
        if ($this->patient_id && ! $patients->contains('patient_id', $this->patient_id)) {
            $patient = PatientInformation::find($this->patient_id);
            if ($patient) {
                $patients->push($patient);
            }
        }
        $selected = $this->appointmentId ? FollowUp::with(['messages' => fn ($query) => $query->latest('id'), 'events' => fn ($query) => $query->latest('id')])->find($this->appointmentId) : null;
        $consultations = MedicalRecord::where('patient_id', $this->patient_id)->latest('consultation_date')->get();
        $attention = FollowUpMessage::with('followUp.patient')->whereIn('status', ['Failed', 'Uncertain'])->latest('id')->limit(5)->get();
        $smsConfigured = app(TextBee::class)->configured();
        $smsPreview = 'Choose a valid date and time to preview the SMS.';
        if (Validator::make($this->only(['date', 'time']), ['date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i']])->passes()) {
            $smsPreview = TextBee::body($selected ? 'Reschedule' : 'Confirmation', CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->date.' '.$this->time, 'Asia/Manila'));
        }

        return view('livewire.follow-ups', compact('appointments', 'patients', 'consultations', 'selected', 'attention', 'smsPreview', 'smsConfigured'));
    }
}
