@php
    $patientFields = [
        'Patient ID' => $patient->patient_id,
        'First name' => $patient->first_name,
        'Middle name' => $patient->middle_name,
        'Last name' => $patient->last_name,
        'Birthdate' => $patient->birthdate,
        'Age' => $patient->age,
        'Sex' => $patient->sex,
        'Address' => $patient->address,
        'Contact number' => $patient->contact_number,
        'Emergency contact' => $patient->emergency_contact,
        'Registration date' => $patient->registration_date,
        'Status' => $patient->status,
        'Created' => $patient->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
        'Last updated' => $patient->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
    ];
@endphp

<div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Medical Record History</h1>
            <p class="mt-2 text-sm text-gray-600">{{ trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->last_name]))) ?: 'Patient' }}</p>
        </div>
        <a href="{{ $backUrl }}" wire:navigate class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Back to Medical Records</a>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="border-b border-gray-200 pb-4 text-lg font-semibold text-gray-900">Patient Information</h2>
        <dl class="mt-5 grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($patientFields as $label => $value)
                <div @class(['min-w-0', 'sm:col-span-2 lg:col-span-3' => $label === 'Address'])>
                    <dt class="font-medium text-gray-500">{{ $label }}</dt>
                    <dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ filled($value) ? $value : 'Not provided.' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section aria-labelledby="consultation-history-heading" class="space-y-4">
        <div>
            <h2 id="consultation-history-heading" class="text-xl font-semibold text-gray-900">Consultation History</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $patient->medicalRecords->count() }} {{ $patient->medicalRecords->count() === 1 ? 'consultation' : 'consultations' }}, newest first</p>
        </div>

        @if ($patient->medicalRecords->isNotEmpty())
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-xs uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3 font-semibold">Consultation Date</th>
                            <th class="px-4 py-3 font-semibold">Attending Doctor</th>
                            <th class="px-4 py-3 font-semibold">Chief Complaint</th>
                            <th class="px-4 py-3 font-semibold">Diagnosis</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($patient->medicalRecords as $record)
                            <tr wire:key="consultation-row-{{ $record->medical_record_id }}">
                                <td class="whitespace-nowrap px-4 py-4 text-gray-900">{{ $record->consultation_date?->format('M j, Y') ?: 'Not provided.' }}</td>
                                <td class="px-4 py-4 text-gray-700">{{ $record->handledBy?->name ?: 'Not provided.' }}</td>
                                <td class="max-w-xs px-4 py-4 text-gray-700"><span class="block truncate" title="{{ $record->consultation_notes ?: 'Not provided.' }}">{{ $record->consultation_notes ?: 'Not provided.' }}</span></td>
                                <td class="max-w-xs px-4 py-4 text-gray-700"><span class="block truncate" title="{{ $record->diagnosis ?: 'Not provided.' }}">{{ $record->diagnosis ?: 'Not provided.' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4 text-right">
                                    <button type="button" x-on:click="$dispatch('open-consultation-modal', { id: {{ $record->medical_record_id }} })" class="rounded-md bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">View All Details</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach ($patient->medicalRecords as $record)
                @php $vitalSign = $record->vitalSign; @endphp
                <div x-data="{ open: false }"
                     x-on:open-consultation-modal.window="if ($event.detail.id === {{ $record->medical_record_id }}) { open = true; $nextTick(() => $refs.closeButton.focus()) }"
                     x-on:keydown.escape.window="open = false">
                    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="consultation-modal-title-{{ $record->medical_record_id }}">
                        <div class="flex min-h-screen items-center justify-center bg-gray-900/50 px-4 py-8" x-on:click.self="open = false">
                            <div x-trap.inert.noscroll="open" class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl sm:p-6">
                                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                                    <div>
                                        <h3 id="consultation-modal-title-{{ $record->medical_record_id }}" class="text-lg font-semibold text-gray-900">Consultation Details</h3>
                                        <p class="mt-1 text-sm text-gray-500">Record #{{ $record->medical_record_id }} · {{ $record->consultation_date?->format('M j, Y') ?: 'Not provided.' }}</p>
                                    </div>
                                    <button x-ref="closeButton" type="button" x-on:click="open = false" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Close</button>
                                </div>

                                <section class="mt-5">
                                    <h4 class="text-base font-semibold text-gray-900">Doctor's Consultation</h4>
                                    <dl class="mt-4 grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ([
                                            'Consultation date' => $record->consultation_date?->format('M j, Y'),
                                            'Attending doctor' => $record->handledBy?->name,
                                            'Chief complaint' => $record->consultation_notes,
                                            'Diagnosis' => $record->diagnosis,
                                            'Treatment plan' => $record->treatment,
                                            'Prescription' => $record->prescription,
                                            'Follow-up date' => $record->follow_up_date?->format('M j, Y'),
                                        ] as $label => $value)
                                            <div @class(['min-w-0', 'sm:col-span-2 lg:col-span-3' => in_array($label, ['Chief complaint', 'Diagnosis', 'Treatment plan', 'Prescription'], true)])>
                                                <dt class="font-medium text-gray-500">{{ $label }}</dt>
                                                <dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ filled($value) ? $value : 'Not provided.' }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </section>

                                <section class="mt-8 border-t border-gray-200 pt-5">
                                    <h4 class="text-base font-semibold text-gray-900">Vital Signs</h4>
                                    <dl class="mt-4 grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ([
                                            'Recording date' => $vitalSign?->recorded_at?->timezone('Asia/Manila')->format('M j, Y'),
                                            'Recording time' => $vitalSign?->recorded_at?->timezone('Asia/Manila')->format('g:i A'),
                                            'Recorded by' => $vitalSign?->recordedBy?->name,
                                            'Blood pressure' => filled($vitalSign?->blood_pressure) ? $vitalSign->blood_pressure.' mmHg' : null,
                                            'Temperature' => $vitalSign?->temperature !== null ? $vitalSign->temperature.' °C' : null,
                                            'Pulse rate' => $vitalSign?->pulse_rate !== null ? $vitalSign->pulse_rate.' bpm' : null,
                                            'Respiratory rate' => $vitalSign?->respiratory_rate !== null ? $vitalSign->respiratory_rate.' breaths/min' : null,
                                            'Height' => $vitalSign?->height !== null ? $vitalSign->height.' cm' : null,
                                            'Weight' => $vitalSign?->weight !== null ? $vitalSign->weight.' kg' : null,
                                            'BMI' => $vitalSign?->bmi,
                                        ] as $label => $value)
                                            <div>
                                                <dt class="font-medium text-gray-500">{{ $label }}</dt>
                                                <dd class="mt-1 text-gray-900">{{ filled($value) ? $value : 'Not provided.' }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500 shadow-sm">No medical records found.</div>
        @endif
    </section>
</div>
