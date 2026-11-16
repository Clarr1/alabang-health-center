@props(['record'])

<div x-data="{}" x-on:keydown.escape.window="$wire.closeMedicalRecordModal()"
     x-init="$nextTick(() => $refs.closeButton.focus())" x-trap.inert.noscroll="true"
     class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="medical-record-modal-title">
    <div wire:click.self="closeMedicalRecordModal" class="flex min-h-screen items-center justify-center bg-gray-900/50 px-4 py-8">
        <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                <div>
                    <h2 id="medical-record-modal-title" class="text-lg font-semibold text-gray-900">Medical Record Details</h2>
                    <p class="mt-1 text-sm text-gray-500">Record #{{ $record->medical_record_id }} · {{ $record->consultation_date?->format('M j, Y') ?: 'Date not available' }}</p>
                </div>
                <button x-ref="closeButton" type="button" wire:click="closeMedicalRecordModal" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>
            </div>

            <div class="mt-5 space-y-5">
                <section class="rounded-lg bg-gray-50 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">Patient Information</h3>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-gray-500">Patient name</dt><dd class="font-medium text-gray-900">{{ trim(implode(' ', array_filter([$record->patient?->first_name, $record->patient?->middle_name, $record->patient?->last_name]))) ?: 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Patient ID</dt><dd class="text-gray-900">{{ $record->patient_id }}</dd></div>
                        <div><dt class="text-gray-500">Sex</dt><dd class="text-gray-900">{{ $record->patient?->sex ?: 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Birthdate</dt><dd class="text-gray-900">{{ $record->patient?->birthdate ?: 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Contact number</dt><dd class="text-gray-900">{{ $record->patient?->contact_number ?: 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Address</dt><dd class="text-gray-900">{{ $record->patient?->address ?: 'Not available' }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-lg border border-gray-200 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">Consultation Details</h3>
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-gray-500">Consultation date</dt><dd class="text-gray-900">{{ $record->consultation_date?->format('M j, Y') ?: 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Attending doctor</dt><dd class="text-gray-900">{{ $record->handledBy?->name ?: 'Not available' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-700">Chief complaint</dt><dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ $record->chief_complaint ?? $record->consultation_notes ?: 'Not recorded' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-700">Symptoms</dt><dd class="mt-1 break-words text-gray-900">{{ implode(', ', $record->symptomNames()) ?: 'Not recorded' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-700">Diagnosis</dt><dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ $record->diagnosis ?: 'Not recorded' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-700">Treatment plan</dt><dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ $record->treatment ?: 'Not recorded' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-700">Prescription</dt><dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ $record->prescription ?: 'Not prescribed' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-700">Laboratory requests</dt><dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ $record->laboratory_requests ?: 'Not recorded' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="font-medium text-gray-700">Consultation notes</dt><dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ $record->consultation_notes ?: 'Not recorded' }}</dd></div>
                        <div><dt class="text-gray-500">Follow-up date</dt><dd class="text-gray-900">{{ $record->follow_up_date?->format('M j, Y') ?: 'Not scheduled' }} @if ($record->follow_up_date) @can('follow-ups-manage') <a href="{{ route('follow-ups.index', ['consultation' => $record->medical_record_id]) }}" class="ml-2 underline">Manage follow-up</a> @endcan @endif</dd></div>
                        <div><dt class="text-gray-500">Created</dt><dd class="text-gray-900">{{ $record->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?: 'Not available' }}</dd></div>
                    </dl>
                </section>

                <x-itr-record-sections :record="$record" />

                <section class="rounded-lg border border-gray-200 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-900">Associated Vital Signs</h3>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-gray-500">Recorded</dt><dd class="text-gray-900">{{ $record->vitalSign?->recorded_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?: 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Recorded by</dt><dd class="text-gray-900">{{ $record->vitalSign?->recordedBy?->name ?: 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Blood pressure</dt><dd class="text-gray-900">{{ $record->vitalSign?->blood_pressure ?: 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Temperature</dt><dd class="text-gray-900">{{ $record->vitalSign?->temperature !== null ? $record->vitalSign->temperature.' °C' : 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Pulse rate</dt><dd class="text-gray-900">{{ $record->vitalSign?->pulse_rate ?? 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Respiratory rate</dt><dd class="text-gray-900">{{ $record->vitalSign?->respiratory_rate ?? 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Height</dt><dd class="text-gray-900">{{ $record->vitalSign?->height !== null ? $record->vitalSign->height.' cm' : 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Weight</dt><dd class="text-gray-900">{{ $record->vitalSign?->weight !== null ? $record->vitalSign->weight.' kg' : 'Not available' }}</dd></div>
                        <div><dt class="text-gray-500">Oxygen saturation (%)</dt><dd>{{ $record->vitalSign?->oxygen_saturation ?? 'Not recorded' }}</dd></div>
                        <div><dt class="text-gray-500">Waistline (cm)</dt><dd>{{ $record->vitalSign?->waistline ?? 'Not recorded' }}</dd></div>
                        <div><dt class="text-gray-500">Nutritional Status</dt><dd>{{ $record->vitalSign?->nutritional_status ?: 'Not recorded' }} ({{ $record->vitalSign?->nutrition_assessment_method ?: 'Method not recorded' }})</dd></div>
                        <div><dt class="text-gray-500">BMI</dt><dd class="text-gray-900">{{ $record->vitalSign?->bmi ?? 'Not available' }}</dd></div>
                    </dl>
                </section>
            </div>
        </div>
    </div>
</div>
