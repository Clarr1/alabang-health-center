<div x-data="{}" x-on:medical-record-saved.window="window.alert(&quot;Medical record saved successfully!&quot;)" class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Doctor&#039;s Consultation</h1>
        <p class="mt-2 text-sm text-gray-600">Select a patient from today's queue to review their vital signs and record a consultation.</p>
    </div>
    @if ($queueId)
        <div class="mb-6 rounded-md bg-blue-50 p-4 text-sm text-blue-700">Consultation for this queue visit. Saving the medical record will complete the visit. <a href="{{ route('queues.index') }}" wire:navigate class="font-semibold underline">Back to Queue</a></div>
    @endif
    @can('queue-consult')
        <section wire:poll.5s class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Doctor&#039;s Consultation Queue</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                    <thead><tr class="text-xs uppercase text-gray-500"><th class="px-3 py-2">Queue Number</th><th class="px-3 py-2">Patient Name</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Action</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->consultationQueue as $entry)
                            <tr wire:key="consultation-queue-{{ $entry->queue_id }}">
                                <td class="px-3 py-3 font-semibold">{{ $entry->queue_number }}</td>
                                <td class="px-3 py-3">{{ $entry->patient->first_name }} {{ $entry->patient->middle_name }} {{ $entry->patient->last_name }}</td>
                                <td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-xs {{ $entry->badgeClasses() }}">{{ $entry->status }}</span></td>
                                <td class="px-3 py-3"><button type="button" wire:click="startConsultation({{ $entry->queue_id }})" wire:loading.attr="disabled" @disabled($queueId !== null) class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">Start Consultation</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-4 text-gray-500">No patients ready for consultation today.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @foreach ($this->activeConsultations as $entry)
                <div wire:key="active-consultation-{{ $entry->queue_id }}" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md bg-blue-50 p-3 text-sm text-blue-700">
                    <span>Queue #{{ $entry->queue_number }} · {{ $entry->patient->first_name }} {{ $entry->patient->last_name }} · In Consultation</span>
                    @if ($queueId !== $entry->queue_id)
                        <button type="button" wire:click="resumeConsultation({{ $entry->queue_id }})" wire:loading.attr="disabled" @disabled($queueId !== null) class="font-semibold underline disabled:opacity-50">Resume Consultation</button>
                    @endif
                </div>
            @endforeach
        </section>
    @endcan

    @if (session('success'))
        <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="space-y-6">
        @if ($patient && $queueId)
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Patient Details</h2>
                <div class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    <p class="font-semibold text-gray-900">{{ $patient->first_name }} {{ $patient->middle_name }} {{ $patient->last_name }}</p>
                    <p class="mt-1">Patient #{{ $patient->patient_id }} &middot; {{ $patient->sex }} &middot; {{ $patient->birthdate }} &middot; Age {{ $patient->age }}</p>
                    <p class="mt-1">{{ $patient->address }} &middot; {{ $patient->contact_number ?: 'No contact number' }}</p>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Current Visit Vital Signs</h2>
                @if ($this->vitalSigns->isEmpty())
                    <p class="text-sm text-gray-500">No vital signs have been recorded for this patient.</p>
                @else
                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-left text-sm"><thead><tr class="text-xs uppercase text-gray-500"><th class="px-3 py-2">Recorded</th><th class="px-3 py-2">Blood Pressure</th><th class="px-3 py-2">Temperature</th><th class="px-3 py-2">Pulse Rate</th><th class="px-3 py-2">Respiratory Rate</th><th class="px-3 py-2">Height</th><th class="px-3 py-2">Weight</th><th class="px-3 py-2">BMI</th><th class="px-3 py-2">Recorded By</th><th class="px-3 py-2">Use</th></tr></thead><tbody class="divide-y divide-gray-100">@foreach ($this->vitalSigns as $vitalSign)<tr><td class="whitespace-nowrap px-3 py-3">{{ $vitalSign->recorded_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?: 'Not recorded' }}</td><td class="px-3 py-3">{{ $vitalSign->blood_pressure ?: '—' }}</td><td class="px-3 py-3">{{ $vitalSign->temperature ?: '—' }}</td><td class="px-3 py-3">{{ $vitalSign->pulse_rate ?? '—' }}</td><td class="px-3 py-3">{{ $vitalSign->respiratory_rate ?? '—' }}</td><td class="px-3 py-3">{{ $vitalSign->height ? $vitalSign->height.' cm' : '—' }}</td><td class="px-3 py-3">{{ $vitalSign->weight ? $vitalSign->weight.' kg' : '—' }}</td><td class="px-3 py-3">{{ $vitalSign->bmi ?: '—' }}</td><td class="px-3 py-3">{{ $vitalSign->recordedBy?->name ?: 'Unknown' }}</td><td class="px-3 py-3"><button type="button" wire:click="selectVitalSign({{ $vitalSign->vital_id }})" @disabled($queueId !== null) class="text-sm font-semibold text-blue-600 hover:text-blue-500">{{ (string) $vital_signs_id === (string) $vitalSign->vital_id ? 'Selected' : 'Select' }}</button></td></tr>@endforeach</tbody></table></div>
                @endif
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Previous Medical Records</h2>
                @forelse ($this->medicalRecords as $record)
                    <article class="border-b border-gray-100 py-4 last:border-b-0"><div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold text-gray-900">{{ $record->diagnosis ?: 'Not available' }}</h3><span class="text-sm text-gray-500">{{ $record->consultation_date?->format('M j, Y') ?: 'Not available' }}</span></div><button type="button" wire:click="viewMedicalRecord({{ $record->medical_record_id }})" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500">View Details</button></div><p class="mt-1 text-sm text-gray-700">{{ $record->treatment ?: 'No treatment recorded.' }}</p><p class="mt-1 text-sm text-gray-500">{{ $record->consultation_notes ?: 'No consultation notes.' }} · Handled by {{ $record->handledBy?->name ?: 'Unknown' }}</p></article>
                @empty
                    <p class="text-sm text-gray-500">No previous medical records.</p>
                @endforelse
            </section>

            @can('queue-consult')
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Doctor&#039;s Consultation Form</h2>
                <form wire:submit="saveMedicalRecord" wire:confirm="Save this medical record and complete the consultation?" class="space-y-5">
                    <div>
                        <label for="consultation_notes" class="mb-1 block text-sm font-medium text-gray-700">Chief Complaint</label>
                        <textarea id="consultation_notes" wire:model="consultation_notes" rows="3" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="diagnosis" class="mb-1 block text-sm font-medium text-gray-700">Diagnosis</label>
                        <textarea id="diagnosis" wire:model="diagnosis" rows="3" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="treatment" class="mb-1 block text-sm font-medium text-gray-700">Treatment Plan</label>
                        <textarea id="treatment" wire:model="treatment" rows="3" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div>
                        <label for="prescription" class="mb-1 block text-sm font-medium text-gray-700">Prescription (optional)</label>
                        <textarea id="prescription" wire:model="prescription" rows="3" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="consultation_date" class="mb-1 block text-sm font-medium text-gray-700">Consultation Date</label>
                            <input id="consultation_date" type="date" wire:model="consultation_date" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="follow_up_date" class="mb-1 block text-sm font-medium text-gray-700">Follow-up Date (optional)</label>
                            <input id="follow_up_date" type="date" wire:model="follow_up_date" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-gray-100 pt-5">
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveMedicalRecord" class="rounded-md bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                            Save Medical Record and Complete Consultation
                        </button>
                    </div>
                </form>
            </section>

            @endcan
        @else
            <p class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">Select a patient from the queue to start consultation.</p>
        @endif
    </div>

@if ($showMedicalRecordModal && $this->selectedMedicalRecord)
    <x-medical-record-details :record="$this->selectedMedicalRecord" />
@endif

</div>
