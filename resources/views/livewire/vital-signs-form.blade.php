<div x-data="{ queueOpen: @js(! $patient) }"
     x-on:vital-signs-patient-selected.window="queueOpen = false; $nextTick(() => $refs.vitalSignsForm.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }))"
     x-on:vital-signs-saved.window="queueOpen = true"
     class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    @php($currentQueue = $this->currentQueue)
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    <div class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Vital signs assessment</h1>
            <p class="mt-2 text-sm text-muted">Record measured values before sending the patient to consultation.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('queues.index') }}" wire:navigate class="patient-button bg-white text-xs">Back to Queue</a>
            <button type="button" x-on:click="queueOpen = !queueOpen" x-bind:aria-expanded="queueOpen" aria-controls="vital-patient-selection" class="patient-button bg-white text-xs">Choose patient</button>
        </div>
    </div>

    @if (session('success'))
        <div role="status" class="relative mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="relative mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section x-cloak x-show="queueOpen" id="vital-patient-selection" class="patient-card relative mb-5" aria-labelledby="vital-queue-title">
        <div wire:poll.5s>
            <h2 id="vital-queue-title" class="text-base font-semibold">Today's Vital Signs Queue</h2>
            <p class="mt-1 text-xs text-muted">Select a patient to call them and start recording vital signs.</p>
            <div class="mt-4 max-h-72 space-y-2 overflow-y-auto">
                @forelse ($this->vitalSignsQueue as $entry)
                    <div wire:key="vital-queue-{{ $entry->queue_id }}-{{ $entry->status }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line p-3">
                        <div class="min-w-0 text-sm">
                            <p class="font-semibold">#{{ $entry->queue_number }} &middot; {{ $entry->patient->first_name }} {{ $entry->patient->middle_name }} {{ $entry->patient->last_name }}</p>
                            <span class="mt-1 inline-block rounded bg-brand-blue px-2 py-1 text-[11px]">{{ $entry->status }}</span>
                        </div>
                        <button type="button" wire:click="selectQueue({{ $entry->queue_id }}, '{{ $entry->status }}')" wire:loading.attr="disabled" wire:target="selectQueue,saveVitalSigns" class="patient-button bg-brand-blue text-xs">Record Vital Signs</button>
                    </div>
                @empty
                    <p class="rounded-lg bg-blush px-4 py-5 text-xs text-muted">No patients waiting for vital signs today.</p>
                @endforelse
            </div>
        </div>
        @if (! $queueId)
            <div class="mt-5 border-t border-line pt-4">
                <label for="patient-search" class="patient-label">Search Patient</label>
                <input id="patient-search" type="search" wire:model.live.debounce.300ms="patientSearch" placeholder="Search by name or patient ID" class="patient-input w-full">
                <p class="mt-2 text-xs text-muted">For a queue visit, use the queue above. You can also record a patient's measurements without a queue visit.</p>
                @if ($patientSearch && $this->patientResults->isNotEmpty())
                    <div class="mt-3 divide-y divide-line overflow-hidden rounded-lg border border-line">
                        @foreach ($this->patientResults as $result)
                            <button type="button" wire:key="vital-patient-{{ $result->patient_id }}" wire:click="selectPatient({{ $result->patient_id }})" x-on:click="queueOpen = false" class="block w-full px-3 py-3 text-left hover:bg-blush focus-visible:outline-pink-400">
                                <span class="block text-sm font-semibold">{{ $result->last_name }}, {{ $result->first_name }} {{ $result->middle_name }}</span>
                                <span class="mt-1 block text-xs text-muted">Patient #{{ $result->patient_id }}</span>
                            </button>
                        @endforeach
                    </div>
                @elseif (trim($patientSearch) !== '')
                    <p class="mt-3 text-xs text-muted">{{ mb_strlen(trim($patientSearch)) < 2 ? 'Enter at least two characters to search.' : 'No matching patients found.' }}</p>
                @endif
            </div>
        @endif
    </section>

    <section class="patient-card relative mb-5 flex flex-wrap items-center justify-between gap-4 !py-4" aria-label="Selected patient">
        @if ($patient)
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-sm font-semibold" aria-hidden="true">{{ mb_strtoupper(mb_substr($patient->first_name, 0, 1).mb_substr($patient->last_name, 0, 1)) }}</span>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold">{{ trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->last_name]))) }}</h2>
                    <p class="mt-1 text-xs leading-relaxed text-muted">Patient #{{ $patient->patient_id }} &middot; {{ $patient->sex }} &middot; {{ $patient->age }} years &middot; {{ \Illuminate\Support\Carbon::parse($patient->birthdate)->format('d M Y') }}</p>
                </div>
            </div>
            @if ($currentQueue)
                <div class="text-xs sm:text-right">
                    <p class="font-semibold">Queue {{ $currentQueue->queue_number }}</p>
                    <p class="mt-1 text-muted">{{ $currentQueue->queue_date->format('d M Y') }} &middot; {{ $currentQueue->created_at->timezone('Asia/Manila')->format('h:i A') }}</p>
                </div>
            @else
                <span class="rounded bg-blush px-3 py-2 text-xs text-muted">Standalone measurement</span>
            @endif
        @else
            <div><h2 class="text-sm font-semibold">No patient selected</h2><p class="mt-1 text-xs text-muted">Select a patient before recording vital signs.</p></div>
        @endif
    </section>

    <form wire:submit.prevent="saveVitalSigns" x-ref="vitalSignsForm" wire:confirm="{{ $queueId ? 'Save these vital signs and send this queue visit to consultation?' : 'Save these vital signs?' }}" class="relative scroll-mt-5">
        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_260px]">
            <div class="min-w-0 space-y-5">
                <section class="patient-card" aria-labelledby="measured-vitals-title">
                    <h2 id="measured-vitals-title" class="text-sm font-semibold">Measured vital signs</h2>
                    <p id="vital-measurement-help" class="mt-1 text-xs text-muted">Use the displayed units. Measurements are optional; enter only values you have measured.</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-6" aria-describedby="vital-measurement-help">
                        <div x-data="bloodPressureMeasurement($wire.entangle('blood_pressure'))" class="grid gap-4 sm:col-span-4 sm:grid-cols-2">
                            <div>
                                <label for="vital-systolic" class="patient-label">Systolic pressure (mmHg)</label>
                                <input id="vital-systolic" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="9" x-model="systolic" x-bind:required="diastolic !== ''" placeholder="e.g. 120" class="patient-input w-full" aria-describedby="blood-pressure-help">
                            </div>
                            <div>
                                <label for="vital-diastolic" class="patient-label">Diastolic pressure (mmHg)</label>
                                <input id="vital-diastolic" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="9" x-model="diastolic" x-bind:required="systolic !== ''" placeholder="e.g. 80" class="patient-input w-full" aria-describedby="blood-pressure-help">
                            </div>
                            <p id="blood-pressure-help" class="sr-only">If recording blood pressure, enter both systolic and diastolic values.</p>
                            <x-input-error :messages="$errors->get('blood_pressure')" class="sm:col-span-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="vital-temperature" class="patient-label">Temperature (&deg;C)</label>
                            <input id="vital-temperature" type="number" step="0.1" min="25" max="45" wire:model.defer="temperature" placeholder="e.g. 36.5" class="patient-input w-full">
                            <x-input-error :messages="$errors->get('temperature')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-3">
                            <label for="vital-pulse" class="patient-label">Pulse (bpm)</label>
                            <input id="vital-pulse" type="number" step="1" min="0" max="220" wire:model.defer="pulse_rate" placeholder="e.g. 72" class="patient-input w-full">
                            <x-input-error :messages="$errors->get('pulse_rate')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-3">
                            <label for="vital-respiratory" class="patient-label">Respiratory rate (breaths/min)</label>
                            <input id="vital-respiratory" type="number" step="1" min="0" max="80" wire:model.defer="respiratory_rate" placeholder="e.g. 18" class="patient-input w-full">
                            <x-input-error :messages="$errors->get('respiratory_rate')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="patient-card" aria-labelledby="anthropometric-title">
                    <h2 id="anthropometric-title" class="text-sm font-semibold">Anthropometric measurements</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="vital-height" class="patient-label">Height (cm)</label>
                            <input id="vital-height" type="number" step="0.01" min="20" max="300" wire:model.live.debounce.300ms="height" class="patient-input w-full">
                            <x-input-error :messages="$errors->get('height')" class="mt-2" />
                        </div>
                        <div>
                            <label for="vital-weight" class="patient-label">Weight (kg)</label>
                            <input id="vital-weight" type="number" step="0.01" min="1" max="500" wire:model.live.debounce.300ms="weight" class="patient-input w-full">
                            <x-input-error :messages="$errors->get('weight')" class="mt-2" />
                        </div>
                        <div>
                            <label for="vital-bmi" class="patient-label">BMI (calculated, kg/m&sup2;)</label>
                            <input id="vital-bmi" type="text" value="{{ $bmi !== '' ? $bmi : '' }}" placeholder="Calculated automatically" readonly class="patient-input w-full bg-[#e9ebf0]" aria-describedby="bmi-help">
                            <x-input-error :messages="$errors->get('bmi')" class="mt-2" />
                        </div>
                    </div>
                    <p id="bmi-help" class="mt-4 text-[11px] leading-relaxed text-muted">BMI = weight (kg) &divide; height (m)&sup2;. Clinical interpretation remains with the care team.</p>
                </section>

                <section class="patient-card" aria-labelledby="assessment-notes-title">
                    <h2 id="assessment-notes-title" class="text-sm font-semibold">Assessment notes</h2>
                    <label for="vital-observations" class="patient-label mt-4">Observations</label>
                    <textarea id="vital-observations" rows="3" disabled aria-describedby="vital-notes-help" placeholder="Assessment notes are not available for vital signs yet." class="patient-input w-full cursor-not-allowed bg-gray-50 disabled:text-muted"></textarea>
                    <p id="vital-notes-help" class="mt-2 text-[11px] text-muted">This form currently saves measured values only.</p>
                </section>
            </div>

            <aside class="min-w-0 space-y-5" aria-label="Assessment guidance and visit status">
                <section class="patient-card" aria-labelledby="vital-visit-title">
                    <h2 id="vital-visit-title" class="text-sm font-semibold">Visit status</h2>
                    <span class="mt-4 inline-block rounded bg-brand-blue px-2 py-1 text-[11px] font-medium">{{ $currentQueue?->status ?: ($patient ? 'Standalone measurement' : 'Select a patient') }}</span>
                    @if ($currentQueue)
                        <p class="mt-4 text-xs">Queue {{ $currentQueue->queue_number }}</p>
                        <p class="mt-3 text-xs text-muted">Saving will mark this visit Ready for Consultation.</p>
                    @else
                        <p class="mt-4 text-xs leading-relaxed text-muted">{{ $patient ? 'No queue visit selected. Select a queue patient to continue to consultation.' : 'Choose a patient from the queue or patient search.' }}</p>
                    @endif
                    <p class="mt-5 text-[11px] text-muted">Recording as</p>
                    <p class="mt-2 text-xs font-semibold">{{ Auth::user()->name }}</p>
                    <p class="mt-1 text-[11px] text-muted">{{ Auth::user()->role }}</p>
                    <label for="vital-recorded-date" class="patient-label mt-5">Recorded date</label>
                    <input id="vital-recorded-date" type="date" value="{{ $recorded_at }}" readonly class="patient-input w-full bg-gray-50 text-xs">
                    <p class="mt-2 text-[11px] text-muted">Asia/Manila</p>
                </section>
                <section class="flex gap-3 rounded-lg border border-line bg-blush/80 px-4 py-4 text-xs">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
                    <div><h3 class="font-semibold">Measurement validation</h3><p class="mt-2 text-[11px] leading-relaxed text-muted">Values are checked against the supported input ranges when saved. Review unexpected readings; clinical interpretation remains with the care team.</p></div>
                </section>
                <section class="flex gap-3 rounded-lg border border-line bg-blush/80 px-4 py-4 text-xs">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
                    <div><h3 class="font-semibold">Before you save</h3><p class="mt-2 text-[11px] leading-relaxed text-muted">Confirm measurements and patient identity. Saving a queue visit sends it to consultation.</p></div>
                </section>
            </aside>
        </div>

        <footer class="mt-5 flex flex-wrap items-center justify-between gap-4">
            <p class="max-w-md text-[11px] leading-relaxed text-muted">Changes are not saved until you submit. <span id="vital-draft-help">Draft saving is not available yet.</span></p>
            <div class="flex flex-wrap gap-2">
                <button type="button" disabled aria-describedby="vital-draft-help" class="patient-button cursor-not-allowed bg-white text-xs">Save draft</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="saveVitalSigns,selectQueue,selectPatient" @disabled(! $patient) aria-label="{{ $queueId ? 'Save Vital Signs and Send to Consultation' : 'Save Vital Signs' }}" class="patient-button border-transparent bg-brand-pink text-xs hover:bg-[#f598ba] disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="saveVitalSigns">{{ $queueId ? 'Save & send to consultation' : 'Save vital signs' }}</span>
                    <span wire:loading wire:target="saveVitalSigns">Saving measurements...</span>
                </button>
            </div>
        </footer>
    </form>
</div>

@if (session('patient_information_saved'))
    @script
        <script>
            alert('Patient information saved successfully!');
        </script>
    @endscript
@endif
