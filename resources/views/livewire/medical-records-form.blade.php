<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Medical Records</h1>
        <p class="mt-2 text-sm text-gray-600">Search for a patient to review their history and record a consultation.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <label for="patient-search" class="mb-1 block text-sm font-medium text-gray-700">Search Patient</label>
            <input id="patient-search" type="search" wire:model.live.debounce.300ms="patientSearch" placeholder="Patient ID, name, QR code, or contact number" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">

            @if ($patientSearch && $this->patientResults->isNotEmpty())
                <div class="mt-2 overflow-hidden rounded-md border border-gray-200">
                    @foreach ($this->patientResults as $result)
                        <button type="button" wire:click="selectPatient({{ $result->patient_id }})" class="block w-full border-b border-gray-100 px-3 py-2 text-left last:border-b-0 hover:bg-gray-50">
                            <span class="block text-sm font-medium text-gray-900">{{ $result->last_name }}, {{ $result->first_name }} {{ $result->middle_name }}</span>
                            <span class="block text-xs text-gray-500">Patient #{{ $result->patient_id }} · {{ $result->qr_code }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif ($patientSearch)
                <p class="mt-2 text-sm text-gray-500">No matching patients found.</p>
            @endif

            @if ($patient)
                <div class="mt-4 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    <p class="font-semibold text-gray-900">{{ $patient->first_name }} {{ $patient->middle_name }} {{ $patient->last_name }}</p>
                    <p class="mt-1">Patient #{{ $patient->patient_id }} · {{ $patient->sex }} · {{ $patient->birthdate }} · Age {{ $patient->age }}</p>
                    <p class="mt-1">{{ $patient->address }} · {{ $patient->contact_number ?: 'No contact number' }}</p>
                </div>
            @endif
        </section>

        @if ($patient)
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Vital Signs History</h2>
                @if ($this->vitalSigns->isEmpty())
                    <p class="text-sm text-gray-500">No vital signs have been recorded for this patient.</p>
                @else
                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-left text-sm"><thead><tr class="text-xs uppercase text-gray-500"><th class="px-3 py-2">Recorded</th><th class="px-3 py-2">Blood Pressure</th><th class="px-3 py-2">Temperature</th><th class="px-3 py-2">Height</th><th class="px-3 py-2">Weight</th><th class="px-3 py-2">BMI</th><th class="px-3 py-2">Recorded By</th><th class="px-3 py-2">Use</th></tr></thead><tbody class="divide-y divide-gray-100">@foreach ($this->vitalSigns as $vitalSign)<tr><td class="whitespace-nowrap px-3 py-3">{{ $vitalSign->recorded_at?->format('M j, Y g:i A') ?: 'Not recorded' }}</td><td class="px-3 py-3">{{ $vitalSign->blood_pressure ?: '—' }}</td><td class="px-3 py-3">{{ $vitalSign->temperature ?: '—' }}</td><td class="px-3 py-3">{{ $vitalSign->height ? $vitalSign->height.' cm' : '—' }}</td><td class="px-3 py-3">{{ $vitalSign->weight ? $vitalSign->weight.' kg' : '—' }}</td><td class="px-3 py-3">{{ $vitalSign->bmi ?: '—' }}</td><td class="px-3 py-3">{{ $vitalSign->recordedBy?->name ?: 'Unknown' }}</td><td class="px-3 py-3"><button type="button" wire:click="selectVitalSign({{ $vitalSign->vital_id }})" class="text-sm font-semibold text-blue-600 hover:text-blue-500">{{ (string) $vital_signs_id === (string) $vitalSign->vital_id ? 'Selected' : 'Select' }}</button></td></tr>@endforeach</tbody></table></div>
                @endif
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">New Consultation</h2>
                <form wire:submit="saveMedicalRecord" class="space-y-5">
                    <div><label for="diagnosis" class="mb-1 block text-sm font-medium text-gray-700">Diagnosis</label><textarea id="diagnosis" wire:model="diagnosis" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea></div>
                    <div><label for="treatment" class="mb-1 block text-sm font-medium text-gray-700">Treatment</label><textarea id="treatment" wire:model="treatment" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea></div>
                    <div><label for="consultation_notes" class="mb-1 block text-sm font-medium text-gray-700">Consultation Notes</label><textarea id="consultation_notes" wire:model="consultation_notes" rows="4" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea></div>
                    <div><label for="consultation_date" class="mb-1 block text-sm font-medium text-gray-700">Consultation Date</label><input id="consultation_date" type="date" wire:model="consultation_date" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></div>
                    <div class="flex justify-end"><button type="submit" class="rounded-md bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">Save Medical Record</button></div>
                </form>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Previous Medical Records</h2>
                @forelse ($this->medicalRecords as $record)
                    <article class="border-b border-gray-100 py-4 last:border-b-0"><div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold text-gray-900">{{ $record->diagnosis ?: 'Not available' }}</h3><span class="text-sm text-gray-500">{{ $record->consultation_date?->format('M j, Y') ?: 'Not available' }}</span></div><button type="button" wire:click="viewMedicalRecord({{ $record->medical_record_id }})" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500">View</button></div><p class="mt-1 text-sm text-gray-700">{{ $record->treatment ?: 'No treatment recorded.' }}</p><p class="mt-1 text-sm text-gray-500">{{ $record->consultation_notes ?: 'No consultation notes.' }} · Handled by {{ $record->handledBy?->name ?: 'Unknown' }}</p></article>
                @empty
                    <p class="text-sm text-gray-500">No previous medical records.</p>
                @endforelse
            </section>
        @else
            <p class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">Select a patient to view their clinical history.</p>
        @endif
    </div>

@if ($showMedicalRecordModal && $this->selectedMedicalRecord)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="medical-record-modal-title">
        <div class="flex min-h-screen items-center justify-center bg-gray-900/50 px-4 py-8">
            <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                    <div><h2 id="medical-record-modal-title" class="text-lg font-semibold text-gray-900">Medical Record Details</h2><p class="mt-1 text-sm text-gray-500">Record #{{ $this->selectedMedicalRecord->medical_record_id }}</p></div>
                    <button type="button" wire:click="closeMedicalRecordModal" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>
                </div>
                <div class="mt-5 grid gap-6 md:grid-cols-2">
                    <div><h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Patient Information</h3><dl class="space-y-1 text-sm text-gray-700"><div><dt class="inline font-medium">Name:</dt> <dd class="inline">{{ trim(implode(' ', array_filter([$this->selectedMedicalRecord->patient?->first_name, $this->selectedMedicalRecord->patient?->middle_name, $this->selectedMedicalRecord->patient?->last_name]))) ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Patient ID:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->patient?->patient_id ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Sex:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->patient?->sex ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Birthdate:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->patient?->birthdate ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Contact:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->patient?->contact_number ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Address:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->patient?->address ?: 'Not available' }}</dd></div></dl></div>
                    <div><h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Record Information</h3><dl class="space-y-1 text-sm text-gray-700"><div><dt class="inline font-medium">Consultation date:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->consultation_date?->format('M j, Y') ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Created:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->created_at?->format('M j, Y g:i A') ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Doctor/healthcare personnel:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->handledBy?->name ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Diagnosis/assessment:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->diagnosis ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Prescription:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->prescription ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Treatment:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->treatment ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Consultation notes:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->consultation_notes ?: 'Not available' }}</dd></div></dl></div>
                    <div class="md:col-span-2"><h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Associated Vital Signs</h3><dl class="grid gap-1 text-sm text-gray-700 sm:grid-cols-2"><div><dt class="inline font-medium">Recorded:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->recorded_at?->format('M j, Y g:i A') ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Recorded by:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->recordedBy?->name ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Blood pressure:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->blood_pressure ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Temperature:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->temperature ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Pulse rate:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->pulse_rate ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Respiratory rate:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->respiratory_rate ?: 'Not available' }}</dd></div><div><dt class="inline font-medium">Height:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->height ? $this->selectedMedicalRecord->vitalSign->height.' cm' : 'Not available' }}</dd></div><div><dt class="inline font-medium">Weight:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->weight ? $this->selectedMedicalRecord->vitalSign->weight.' kg' : 'Not available' }}</dd></div><div><dt class="inline font-medium">BMI:</dt> <dd class="inline">{{ $this->selectedMedicalRecord->vitalSign?->bmi ?: 'Not available' }}</dd></div></dl></div>
                </div>
            </div>
        </div>
    </div>
@endif

</div>

@script
    <script>
        $wire.on('medical-record-saved', () => {
            alert('Medical record saved successfully!');
            $wire.call('resetForm');
        });
    </script>
@endscript