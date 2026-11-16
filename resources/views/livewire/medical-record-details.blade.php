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

<div x-data="{
        activeTab: 'visits',
        selectTab(tab) { this.activeTab = tab; this.$nextTick(() => this.$refs[tab + 'Tab'].focus()) },
        moveTab(offset) { const tabs = ['visits', 'vitals', 'followups']; this.selectTab(tabs[(tabs.indexOf(this.activeTab) + offset + tabs.length) % tabs.length]) }
     }" @class(['relative mx-auto max-w-[1440px] text-ink', 'px-4 py-7 sm:px-7 lg:px-8 lg:py-8' => ! $embedded])>
    @php
        $latestVital = $patient->vitalSigns->first();
        $latestVisit = $patient->medicalRecords->first();
        $followUps = $patient->medicalRecords->whereNotNull('follow_up_date')->sortByDesc('follow_up_date');
    @endphp
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    @if ($familyHouseholdId !== null && ! $embedded)
        <nav aria-label="Breadcrumb" class="relative mb-4 text-xs text-muted"><ol class="flex flex-wrap gap-2"><li><a href="{{ route('patients.index') }}" wire:navigate>Patient Record</a></li><li>/</li><li><a href="{{ route('families.index') }}" wire:navigate>Family Records</a></li><li>/</li><li><a href="{{ $backUrl }}" wire:navigate>{{ $patient->household->label }} ({{ $patient->household->family_number }})</a></li><li>/</li><li aria-current="page">{{ $patient->first_name }} {{ $patient->last_name }}</li></ol></nav>
    @endif
    <div class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            @if ($embedded)<h3 class="text-xl font-semibold tracking-tight">Patient medical record</h3>@else<h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Patient medical record</h1>@endif
            <p class="mt-2 text-sm text-muted">Longitudinal history &middot; Access restricted to care-related work.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $backUrl }}" wire:navigate class="patient-button bg-white text-xs">{{ $familyHouseholdId === null ? 'Back to Medical Records' : 'Back to Family Record' }}</a>
            @if ($familyHouseholdId === null && $patient->household)
                <a href="{{ route('families.show', $patient->household) }}" wire:navigate class="patient-button bg-white text-xs">View Family Record &middot; {{ $patient->household->family_number }}</a>
            @endif
            @can('queue-consult')
                <a href="{{ route('doctors-consultation.index') }}" wire:navigate class="patient-button border-transparent bg-brand-pink text-xs hover:bg-[#f598ba]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    New consultation
                </a>
            @endcan
        </div>
    </div>

    <section class="patient-card relative mb-5 flex flex-wrap items-center justify-between gap-4 !py-4" aria-label="Patient summary">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-sm font-semibold" aria-hidden="true">{{ mb_strtoupper(mb_substr($patient->first_name, 0, 1).mb_substr($patient->last_name, 0, 1)) }}</span>
            <div class="min-w-0"><h2 class="text-sm font-semibold">{{ trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->last_name]))) }}</h2><p class="mt-1 text-xs leading-relaxed text-muted">Patient #{{ $patient->patient_id }} &middot; {{ $patient->sex }} &middot; {{ $patient->age }} years &middot; <time datetime="{{ $patient->birthdate }}">{{ \Illuminate\Support\Carbon::parse($patient->birthdate)->format('d M Y') }}</time></p></div>
        </div>
        @if ($latestQueue)
            <div class="text-xs sm:text-right"><p class="font-semibold">Latest queue {{ $latestQueue->queue_number }}</p><p class="mt-1 text-muted">{{ $latestQueue->queue_date->format('d M Y') }} &middot; {{ $latestQueue->created_at->timezone('Asia/Manila')->format('h:i A') }}</p></div>
        @endif
    </section>

    <div class="relative grid items-start gap-5 xl:grid-cols-[280px_minmax(0,1fr)]">
        <aside class="min-w-0 space-y-5" aria-label="Patient demographics and relevant history">
            <section class="patient-card" aria-labelledby="demographic-overview-title">
                <h2 id="demographic-overview-title" class="text-sm font-semibold">Demographic overview</h2>
                <p class="mt-4 whitespace-pre-line break-words text-xs leading-relaxed">{{ filled($patient->address) ? $patient->address : 'Not provided.' }}</p>
                <dl class="mt-4 space-y-4 text-xs">
                    <div><dt class="text-[11px] text-muted">Mobile</dt><dd class="mt-1 break-words">{{ filled($patient->contact_number) ? $patient->contact_number : 'Not provided.' }}</dd></div>
                    <div><dt class="text-[11px] text-muted">Emergency contact</dt><dd class="mt-1 whitespace-pre-line break-words">{{ filled($patient->emergency_contact) ? $patient->emergency_contact : 'Not provided.' }}</dd></div>
                    <div><dt class="text-[11px] text-muted">Registered</dt><dd class="mt-1"><time datetime="{{ $patient->registration_date }}">{{ $patient->registration_date ? \Illuminate\Support\Carbon::parse($patient->registration_date)->format('d M Y') : 'Not provided.' }}</time></dd></div>
                    <div><dt class="sr-only">Status</dt><dd><span class="rounded bg-[#e9ebf0] px-2 py-1 text-[11px]">{{ filled($patient->status) ? $patient->status : 'Not provided.' }}</span></dd></div>
                </dl>
                <details wire:ignore.self class="mt-5 border-t border-line pt-4">
                    <summary class="cursor-pointer text-xs font-semibold focus-visible:outline-pink-400">Patient Information</summary>
                    <dl class="mt-4 space-y-3 text-xs">
                        @foreach ($patientFields as $label => $value)
                            <div class="min-w-0"><dt class="text-[11px] text-muted">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line break-words">{{ filled($value) ? $value : 'Not provided.' }}</dd></div>
                        @endforeach
                    </dl>
                </details>
            </section>
            <section class="patient-card" aria-labelledby="relevant-history-title">
                <h2 id="relevant-history-title" class="text-sm font-semibold">Relevant history</h2>
                <p class="mt-4 text-xs leading-relaxed">Reported conditions and registration history are available in the patient registration profile below.</p>
                @if ($latestVisit)
                    <p class="mt-4 break-words text-xs leading-relaxed"><span class="font-medium">Previous visit:</span> {{ $latestVisit->diagnosis ?: 'Not provided.' }}, {{ $latestVisit->consultation_date?->format('d M Y') ?: 'Not provided.' }}</p>
                @else
                    <p class="mt-4 text-xs text-muted">No medical records found.</p>
                @endif
                <p class="mt-4 text-[11px] leading-relaxed text-muted">Review the saved visits for documented history. New consultations begin from today's consultation queue.</p>
            </section>
        </aside>

        <div class="min-w-0">
            <section class="patient-card" aria-labelledby="latest-vitals-title">
                <h2 id="latest-vitals-title" class="text-sm font-semibold">Latest vital signs</h2>
                @if ($latestVital)
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">{{ $latestVital->recorded_at?->timezone('Asia/Manila')->format('d M Y, h:i A') ?: 'Not provided.' }} &middot; {{ $latestVital->recordedBy?->name ?: 'Not provided.' }}</p>
                    <dl class="mt-5 grid gap-4 sm:grid-cols-3 text-xs">
                        <div><dt class="text-[11px] text-muted">BP</dt><dd class="mt-1 font-semibold">@if (filled($latestVital->blood_pressure)){{ $latestVital->blood_pressure }} mmHg @else Not provided. @endif</dd></div>
                        <div><dt class="text-[11px] text-muted">Temperature</dt><dd class="mt-1 font-semibold">@if ($latestVital->temperature !== null){{ $latestVital->temperature }} &deg;C @else Not provided. @endif</dd></div>
                        <div><dt class="text-[11px] text-muted">Pulse</dt><dd class="mt-1 font-semibold">@if ($latestVital->pulse_rate !== null){{ $latestVital->pulse_rate }} bpm @else Not provided. @endif</dd></div>
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-x-2 gap-y-1 text-[11px] text-muted">
                        <span>RR: @if ($latestVital->respiratory_rate !== null){{ $latestVital->respiratory_rate }} breaths/min @else Not provided. @endif</span>
                        <span>&middot; Height: @if ($latestVital->height !== null){{ $latestVital->height }} cm @else Not provided. @endif</span>
                        <span>&middot; Weight: @if ($latestVital->weight !== null){{ $latestVital->weight }} kg @else Not provided. @endif</span>
                        <span>&middot; BMI: {{ $latestVital->bmi ?? 'Not provided.' }}</span>
                    </div>
                @else
                    <p class="mt-4 text-xs text-muted">No vital signs recorded.</p>
                @endif
            </section>

            <div role="tablist" aria-label="Patient record history" class="mt-5 flex gap-5 border-b border-line text-xs"
                 x-on:keydown.right.prevent="moveTab(1)" x-on:keydown.left.prevent="moveTab(-1)"
                 x-on:keydown.home.prevent="selectTab('visits')" x-on:keydown.end.prevent="selectTab('followups')">
                <button x-ref="visitsTab" id="record-visits-tab" type="button" role="tab" aria-controls="record-visits-panel" x-bind:aria-selected="activeTab === 'visits'" x-bind:tabindex="activeTab === 'visits' ? 0 : -1" x-on:click="selectTab('visits')" x-bind:class="activeTab === 'visits' ? 'border-brand-blue text-ink' : 'border-transparent text-muted'" class="-mb-px border-b-2 py-3 font-medium focus-visible:outline-pink-400">Visits</button>
                <button x-ref="vitalsTab" id="record-vitals-tab" type="button" role="tab" aria-controls="record-vitals-panel" x-bind:aria-selected="activeTab === 'vitals'" x-bind:tabindex="activeTab === 'vitals' ? 0 : -1" x-on:click="selectTab('vitals')" x-bind:class="activeTab === 'vitals' ? 'border-brand-blue text-ink' : 'border-transparent text-muted'" class="-mb-px border-b-2 py-3 font-medium focus-visible:outline-pink-400">Vitals</button>
                <button x-ref="followupsTab" id="record-followups-tab" type="button" role="tab" aria-controls="record-followups-panel" x-bind:aria-selected="activeTab === 'followups'" x-bind:tabindex="activeTab === 'followups' ? 0 : -1" x-on:click="selectTab('followups')" x-bind:class="activeTab === 'followups' ? 'border-brand-blue text-ink' : 'border-transparent text-muted'" class="-mb-px border-b-2 py-3 font-medium focus-visible:outline-pink-400">Follow-ups</button>
            </div>

            <section x-show="activeTab === 'visits'" id="record-visits-panel" role="tabpanel" aria-labelledby="record-visits-tab" class="patient-card mt-5">
                <h2 class="text-base font-semibold">Medical history</h2>
                <p class="mt-1 text-[11px] leading-relaxed text-muted">Saved entries are read-only here. Addendum and correction workflows are not available yet.</p>
                <p class="mt-2 text-[11px] text-muted">{{ $patient->medicalRecords->count() }} {{ $patient->medicalRecords->count() === 1 ? 'consultation' : 'consultations' }}, newest first</p>
                <div class="mt-4 divide-y divide-line">
                    @forelse ($patient->medicalRecords as $record)
                        <article wire:key="consultation-row-{{ $record->medical_record_id }}" class="py-5 first:pt-0 last:pb-0">
                            <div class="flex flex-wrap items-start justify-between gap-3"><h3 class="min-w-0 break-words text-sm font-semibold">{{ $record->consultation_date?->format('d M Y') ?: 'Not provided.' }} &middot; {{ filled($record->diagnosis) ? $record->diagnosis : 'Not provided.' }}</h3><span class="rounded bg-[#e9ebf0] px-2 py-1 text-[10px] font-medium">{{ $record->queue?->status === 'Completed' ? 'Finalized' : 'Saved' }}</span></div>
                            <p class="mt-2 text-[11px] text-muted">{{ $record->handledBy?->name ?: 'Not provided.' }} &middot; Saved {{ $record->created_at?->timezone('Asia/Manila')->format('d M Y') ?: 'Not provided.' }}</p>
                            <p class="mt-3 whitespace-pre-line break-words text-xs leading-relaxed"><span class="font-medium">Prescription:</span> {{ filled($record->prescription) ? $record->prescription : 'Not provided.' }}</p>
                            <p class="mt-2 whitespace-pre-line break-words text-xs leading-relaxed"><span class="font-medium">Chief complaint:</span> {{ $record->chief_complaint ?? $record->consultation_notes ?: 'Not provided.' }}</p>
                            <p class="mt-2 break-words text-xs leading-relaxed"><span class="font-medium">Symptoms:</span> {{ implode(', ', $record->symptomNames()) ?: 'Not recorded' }}</p>
                            <p class="mt-2 whitespace-pre-line break-words text-xs leading-relaxed"><span class="font-medium">Laboratory requests:</span> {{ $record->laboratory_requests ?: 'Not recorded' }}</p>
                            <p class="mt-2 whitespace-pre-line break-words text-xs leading-relaxed"><span class="font-medium">Treatment / notes:</span> {{ filled($record->treatment) ? $record->treatment : 'Not provided.' }}</p>
                            <p class="mt-2 whitespace-pre-line break-words text-xs leading-relaxed text-muted">{{ filled($record->consultation_notes) ? $record->consultation_notes : 'Not provided.' }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" aria-label="View All Details" x-on:click="$el.focus(); $dispatch('open-consultation-modal', { id: {{ $record->medical_record_id }} })" class="patient-button px-3 py-2 text-xs">View original entry</button>
                                <button type="button" disabled class="patient-button cursor-not-allowed px-3 py-2 text-xs" title="Addenda and corrections are not available yet.">Addendum / correction</button>
                            </div>
                        </article>
                    @empty
                        <p class="py-5 text-xs text-muted">No medical records found.</p>
                    @endforelse
                </div>
            </section>

            <section x-cloak x-show="activeTab === 'vitals'" id="record-vitals-panel" role="tabpanel" aria-labelledby="record-vitals-tab" class="patient-card mt-5">
                <h2 class="text-base font-semibold">Vital signs history</h2>
                <p class="mt-1 text-[11px] text-muted">All recorded measurements, newest first.</p>
                @if ($patient->vitalSigns->isNotEmpty())
                    <div class="mt-4 overflow-x-auto rounded-t-md" tabindex="0" role="region" aria-label="Vital signs history table, scroll horizontally for all columns">
                        <table class="w-full min-w-[850px] text-left text-xs">
                            <thead class="bg-blush text-[11px]"><tr><th scope="col" class="px-3 py-3">Recorded</th><th scope="col" class="px-3 py-3">BP (mmHg)</th><th scope="col" class="px-3 py-3">Temperature (&deg;C)</th><th scope="col" class="px-3 py-3">Pulse (bpm)</th><th scope="col" class="px-3 py-3">RR (/min)</th><th scope="col" class="px-3 py-3">Height (cm)</th><th scope="col" class="px-3 py-3">Weight (kg)</th><th scope="col" class="px-3 py-3">BMI</th><th scope="col" class="px-3 py-3">O2 Sat (%)</th><th scope="col" class="px-3 py-3">Waistline (cm)</th><th scope="col" class="px-3 py-3">Nutritional Status</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($patient->vitalSigns as $measurement)
                                    <tr wire:key="patient-vital-{{ $measurement->vital_id }}"><td class="whitespace-nowrap px-3 py-4">{{ $measurement->recorded_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?: 'Not provided.' }}<span class="mt-1 block text-[11px] text-muted">{{ $measurement->recordedBy?->name ?: 'Not provided.' }}</span></td><td class="px-3 py-4">{{ filled($measurement->blood_pressure) ? $measurement->blood_pressure : 'Not provided.' }}</td><td class="px-3 py-4">{{ $measurement->temperature ?? 'Not provided.' }}</td><td class="px-3 py-4">{{ $measurement->pulse_rate ?? 'Not provided.' }}</td><td class="px-3 py-4">{{ $measurement->respiratory_rate ?? 'Not provided.' }}</td><td class="px-3 py-4">{{ $measurement->height ?? 'Not provided.' }}</td><td class="px-3 py-4">{{ $measurement->weight ?? 'Not provided.' }}</td><td class="px-3 py-4">{{ $measurement->bmi ?? 'Not provided.' }}</td><td class="px-3 py-4">{{ $measurement->oxygen_saturation ?? 'Not recorded' }}</td><td class="px-3 py-4">{{ $measurement->waistline ?? 'Not recorded' }}</td><td class="px-3 py-4">{{ $measurement->nutritional_status ?: 'Not recorded' }}<span class="block text-muted">{{ $measurement->nutrition_assessment_method }}</span></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-4 text-xs text-muted">No vital signs recorded.</p>
                @endif
            </section>

            <section x-cloak x-show="activeTab === 'followups'" id="record-followups-panel" role="tabpanel" aria-labelledby="record-followups-tab" class="patient-card mt-5">
                <h2 class="text-base font-semibold">Scheduled follow-ups</h2>
                <div class="mb-6 mt-4 divide-y divide-line">
                    @forelse ($patient->followUps as $appointment)
                        <article wire:key="scheduled-follow-up-{{ $appointment->id }}" class="py-4 first:pt-0 last:pb-0">
                            <h3 class="text-sm font-semibold">{{ $appointment->scheduled_at->timezone('Asia/Manila')->format('d M Y, h:i A') }} &middot; {{ $appointment->status }}</h3>
                            <p class="mt-2 whitespace-pre-line break-words text-xs">{{ $appointment->purpose }}</p>
                            @if ($appointment->consultation)
                                <p class="mt-2 text-xs text-muted">{{ $appointment->consultation->diagnosis }}</p>
                                <button type="button" x-on:click="$el.focus(); $dispatch('open-consultation-modal', { id: {{ $appointment->consultation->medical_record_id }} })" class="patient-button mt-3 text-xs">View associated visit</button>
                            @endif
                            @can('follow-ups-manage')<a href="{{ route('follow-ups.index', $appointment->consultation ? ['consultation' => $appointment->consultation->medical_record_id] : []) }}" wire:navigate class="patient-button mt-3 text-xs">Manage follow-up</a>@endcan
                        </article>
                    @empty
                        <p class="text-xs text-muted">No scheduled follow-ups.</p>
                    @endforelse
                </div>
                <h2 class="text-base font-semibold">Recorded follow-ups</h2>
                <p class="mt-1 text-[11px] text-muted">Dates documented in saved consultations.</p>
                <div class="mt-4 divide-y divide-line">
                    @forelse ($followUps as $followUp)
                        <article wire:key="patient-follow-up-{{ $followUp->medical_record_id }}" class="py-4 first:pt-0 last:pb-0"><h3 class="text-sm font-semibold">{{ $followUp->follow_up_date->format('d M Y') }}</h3><p class="mt-2 break-words text-xs">{{ $followUp->diagnosis ?: 'Not provided.' }}</p><p class="mt-1 text-[11px] text-muted">Consultation: {{ $followUp->consultation_date?->format('d M Y') ?: 'Not provided.' }} &middot; {{ $followUp->handledBy?->name ?: 'Not provided.' }}</p><button type="button" x-on:click="$el.focus(); $dispatch('open-consultation-modal', { id: {{ $followUp->medical_record_id }} })" class="patient-button mt-3 px-3 py-2 text-xs">View associated visit</button> @can('follow-ups-manage')<a href="{{ route('follow-ups.index', ['consultation' => $followUp->medical_record_id]) }}" class="patient-button mt-3 px-3 py-2 text-xs">Manage follow-up</a>@endcan</article>
                    @empty
                        <p class="text-xs text-muted">No follow-ups recorded.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

            <livewire:patient-profile-form :patient-id="$patient->patient_id" :family-household-id="$familyHouseholdId" :key="'registration-profile-'.($familyHouseholdId ?? 'individual').'-'.$patient->patient_id" />

            @foreach ($patient->medicalRecords as $record)
                @php $vitalSign = $record->vitalSign; @endphp
                <div x-data="{ open: false }"
                     x-on:open-consultation-modal.window="if ($event.detail.id === {{ $record->medical_record_id }}) { open = true }"
                     x-on:keydown.escape.window="open = false">
                    <div x-cloak x-show="open" x-transition.opacity class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="consultation-modal-title-{{ $record->medical_record_id }}">
                        <div class="flex min-h-screen items-center justify-center bg-gray-900/50 px-4 py-8" x-on:click.self="open = false">
                            <div x-trap.inert.noscroll="open" class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl sm:p-6">
                                <div class="flex items-start justify-between gap-4 border-b border-line pb-4">
                                    <div>
                                        <h3 id="consultation-modal-title-{{ $record->medical_record_id }}" class="text-lg font-semibold text-ink">Consultation Details</h3>
                                        <p class="mt-1 text-sm text-muted">Record #{{ $record->medical_record_id }} · {{ $record->consultation_date?->format('M j, Y') ?: 'Not provided.' }}</p>
                                    </div>
                                    <button x-ref="closeButton" type="button" x-on:click="open = false" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-ink hover:bg-blush focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Close</button>
                                </div>

                                <section class="mt-5">
                                    <h4 class="text-base font-semibold text-ink">Doctor's Consultation</h4>
                                    <dl class="mt-4 grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                        @foreach ([
                                            'Consultation date' => $record->consultation_date?->format('M j, Y'),
                                            'Attending doctor' => $record->handledBy?->name,
                                            'Chief complaint' => $record->chief_complaint ?? $record->consultation_notes,
                                            'Symptoms' => implode(', ', $record->symptomNames()),
                                            'Diagnosis' => $record->diagnosis,
                                            'Treatment plan' => $record->treatment,
                                            'Prescription' => $record->prescription,
                                            'Laboratory requests' => $record->laboratory_requests,
                                            'Consultation notes' => $record->consultation_notes,
                                            'Follow-up date' => $record->follow_up_date?->format('M j, Y'),
                                        ] as $label => $value)
                                            <div @class(['min-w-0', 'sm:col-span-2 lg:col-span-3' => in_array($label, ['Chief complaint', 'Symptoms', 'Diagnosis', 'Treatment plan', 'Prescription', 'Laboratory requests', 'Consultation notes'], true)])>
                                                <dt class="font-medium text-muted">{{ $label }}</dt>
                                                <dd class="mt-1 whitespace-pre-line break-words text-ink">{{ filled($value) ? $value : 'Not provided.' }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </section>

                                <x-itr-record-sections :record="$record" />

                                <section class="mt-8 border-t border-line pt-5">
                                    <h4 class="text-base font-semibold text-ink">Vital Signs</h4>
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
                                            'Oxygen saturation (%)' => $vitalSign?->oxygen_saturation,
                                            'Waistline (cm)' => $vitalSign?->waistline,
                                            'Nutritional Status' => $vitalSign?->nutritional_status,
                                            'Nutrition assessment method' => $vitalSign?->nutrition_assessment_method,
                                        ] as $label => $value)
                                            <div>
                                                <dt class="font-medium text-muted">{{ $label }}</dt>
                                                <dd class="mt-1 text-ink">{{ filled($value) ? $value : 'Not provided.' }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
</div>
