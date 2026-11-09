<div x-data="{ queueOpen: @js(! $queueId), historyOpen: false }"
     x-on:medical-record-saved.window="window.alert(&quot;Medical record saved successfully!&quot;); historyOpen = false"
     x-on:queue-updated.window="queueOpen = ! $wire.queueId"
     class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    @php
        $currentQueue = $this->currentQueue;
        $visitVital = $this->vitalSigns->firstWhere('vital_id', (int) $vital_signs_id);
        $latestRecord = $this->medicalRecords->first();
    @endphp
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    <div class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Consultation workspace</h1>
            <p class="mt-2 text-sm text-muted">Document today's care. Complete the consultation only after reviewing all entries.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('queues.index') }}" wire:navigate class="patient-button bg-white text-xs">Back to Queue</a>
            <button type="button" x-on:click="queueOpen = !queueOpen" x-bind:aria-expanded="queueOpen" aria-controls="consultation-patient-selection" class="patient-button bg-white text-xs">Consultation queue</button>
            @if ($queueId)<span class="rounded bg-[#e9ebf0] px-2 py-1 text-[11px] font-semibold">Unsaved</span>@endif
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

    <section x-cloak x-show="queueOpen" id="consultation-patient-selection" class="patient-card relative mb-5" aria-labelledby="consultation-queue-title">
        <div wire:poll.5s>
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div><h2 id="consultation-queue-title" class="text-base font-semibold">Doctor&#039;s Consultation Queue</h2><p class="mt-1 text-xs text-muted">Select a patient whose assessment is ready, or resume your consultation.</p></div>
                <span wire:loading.delay wire:target="startConsultation,resumeConsultation" role="status" class="text-xs text-muted">Opening consultation...</span>
            </div>
            <div class="max-h-72 space-y-2 overflow-y-auto">
                @forelse ($this->consultationQueue as $entry)
                    <div wire:key="consultation-queue-{{ $entry->queue_id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line p-3">
                        <div class="min-w-0"><p class="text-sm font-semibold">#{{ $entry->queue_number }} &middot; {{ $entry->patient->first_name }} {{ $entry->patient->middle_name }} {{ $entry->patient->last_name }}</p><span class="mt-1 inline-block rounded bg-brand-blue px-2 py-1 text-[11px]">{{ $entry->status }}</span></div>
                        <button type="button" wire:click="startConsultation({{ $entry->queue_id }})" wire:loading.attr="disabled" @disabled($queueId !== null) class="patient-button bg-brand-pink text-xs disabled:cursor-not-allowed">Start Consultation</button>
                    </div>
                @empty
                    <p class="rounded-lg bg-blush px-4 py-5 text-xs text-muted">No patients ready for consultation today.</p>
                @endforelse
                @foreach ($this->activeConsultations as $entry)
                    <div wire:key="active-consultation-{{ $entry->queue_id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-blush p-3">
                        <div class="min-w-0"><p class="text-sm font-semibold">Queue #{{ $entry->queue_number }} &middot; {{ $entry->patient->first_name }} {{ $entry->patient->last_name }}</p><span class="mt-1 inline-block rounded bg-brand-pink px-2 py-1 text-[11px]">In Consultation</span></div>
                        @if ($queueId !== $entry->queue_id)
                            <button type="button" wire:click="resumeConsultation({{ $entry->queue_id }})" wire:loading.attr="disabled" @disabled($queueId !== null) class="patient-button bg-white text-xs disabled:cursor-not-allowed">Resume Consultation</button>
                        @else
                            <span class="text-xs text-muted">Currently selected</span>
                        @endif
                    </div>
                @endforeach
            </div>
            @if ($queueId)<p class="mt-3 text-xs text-muted">Complete the selected consultation before starting another.</p>@endif
        </div>
    </section>

    @if ($patient && $queueId)
        <section class="patient-card relative mb-5 flex flex-wrap items-center justify-between gap-4 !py-4" aria-label="Patient Details">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-blue text-sm font-semibold" aria-hidden="true">{{ mb_strtoupper(mb_substr($patient->first_name, 0, 1).mb_substr($patient->last_name, 0, 1)) }}</span>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold">{{ trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->last_name]))) }}</h2>
                    <p class="mt-1 text-xs leading-relaxed text-muted">Patient #{{ $patient->patient_id }} &middot; {{ $patient->sex }} &middot; {{ $patient->age }} years &middot; {{ \Illuminate\Support\Carbon::parse($patient->birthdate)->format('d M Y') }}</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-muted">{{ $patient->address }} &middot; {{ $patient->contact_number ?: 'No contact number' }}</p>
                </div>
            </div>
            @if ($currentQueue)
                <div class="text-xs sm:text-right"><p class="font-semibold">Queue {{ $currentQueue->queue_number }}</p><p class="mt-1 text-muted">{{ $currentQueue->queue_date->format('d M Y') }} &middot; {{ $currentQueue->created_at->timezone('Asia/Manila')->format('h:i A') }}</p></div>
            @endif
        </section>

        <form aria-label="Doctor&#039;s Consultation Form" wire:submit="saveMedicalRecord" wire:confirm="Save this medical record and complete the consultation?" class="relative">
            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
                <div class="min-w-0 space-y-5">
                    <section class="patient-card" aria-labelledby="consultation-vitals-title">
                        <h2 id="consultation-vitals-title" class="text-sm font-semibold">Latest vitals <span class="sr-only">Current Visit Vital Signs</span></h2>
                        @if ($visitVital)
                            <p class="mt-2 text-[11px] leading-relaxed text-muted">{{ $visitVital->recorded_at?->timezone('Asia/Manila')->format('d M Y, h:i A') ?: 'Time not recorded' }} &middot; {{ $visitVital->recordedBy?->name ?: 'Unknown' }}</p>
                            <dl class="mt-4 space-y-3 text-xs">
                                <div><dt class="inline">BP </dt><dd class="inline">@if ($visitVital->blood_pressure){{ $visitVital->blood_pressure }} mmHg@else Not recorded @endif</dd></div>
                                <div><dt class="inline">Temperature </dt><dd class="inline">@if ($visitVital->temperature !== null){{ $visitVital->temperature }} &deg;C@else Not recorded @endif</dd></div>
                                <div><dt class="inline">Pulse </dt><dd class="inline">@if ($visitVital->pulse_rate !== null){{ $visitVital->pulse_rate }} bpm@else Not recorded @endif</dd></div>
                                <div><dt class="inline">Respiratory rate </dt><dd class="inline">@if ($visitVital->respiratory_rate !== null){{ $visitVital->respiratory_rate }} /min@else Not recorded @endif</dd></div>
                                <div><dt class="inline">Height </dt><dd class="inline">@if ($visitVital->height !== null){{ $visitVital->height }} cm@else Not recorded @endif</dd></div>
                                <div><dt class="inline">Weight </dt><dd class="inline">@if ($visitVital->weight !== null){{ $visitVital->weight }} kg@else Not recorded @endif</dd></div>
                                <div><dt class="inline">BMI </dt><dd class="inline">{{ $visitVital->bmi ?? 'Not recorded' }}</dd></div>
                            </dl>
                        @else
                            <p class="mt-3 text-xs text-muted">No vital signs have been recorded for this patient.</p>
                        @endif
                    </section>

                    <section x-cloak x-show="historyOpen" x-ref="historySection" id="consultation-history" class="patient-card scroll-mt-5" aria-labelledby="previous-records-title">
                        <h2 id="previous-records-title" class="text-sm font-semibold">Previous Medical Records</h2>
                        <div class="mt-3 divide-y divide-line">
                            @forelse ($this->medicalRecords as $record)
                                <article wire:key="consultation-history-{{ $record->medical_record_id }}" class="py-4 first:pt-0 last:pb-0">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0"><h3 class="break-words text-sm font-semibold">{{ $record->diagnosis ?: 'Not available' }}</h3><p class="mt-1 text-[11px] text-muted">{{ $record->consultation_date?->format('d M Y') ?: 'Date not available' }} &middot; {{ $record->handledBy?->name ?: 'Unknown' }}</p></div>
                                        <button type="button" wire:click="viewMedicalRecord({{ $record->medical_record_id }})" wire:loading.attr="disabled" class="patient-button px-3 py-2 text-xs">View Details</button>
                                    </div>
                                    <p class="mt-2 whitespace-pre-line break-words text-xs">{{ $record->treatment ?: 'No treatment recorded.' }}</p>
                                    <p class="mt-2 whitespace-pre-line break-words text-xs"><span class="font-medium">Chief complaint:</span> {{ $record->chief_complaint ?? $record->consultation_notes ?: 'Not recorded' }}</p>
                                    <p class="mt-2 break-words text-xs"><span class="font-medium">Symptoms:</span> {{ implode(', ', $record->symptomNames()) ?: 'Not recorded' }}</p>
                                    <p class="mt-1 whitespace-pre-line break-words text-xs text-muted">{{ $record->consultation_notes ?: 'No consultation notes.' }}</p>
                                </article>
                            @empty
                                <p class="text-xs text-muted">No previous medical records.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="patient-card" aria-labelledby="clinical-documentation-title">
                        <h2 id="clinical-documentation-title" class="text-sm font-semibold">Clinical documentation</h2>
                        <p class="mt-1 text-xs text-muted">{{ now('Asia/Manila')->format('d M Y') }} &middot; {{ Auth::user()->name }}</p>
                        <div class="mt-5 space-y-4">
                            <div>
                                <label for="chief_complaint" class="patient-label">Chief Complaint</label>
                                <textarea id="chief_complaint" wire:model="chief_complaint" rows="4" maxlength="10000" class="patient-input w-full"></textarea>
                                <x-input-error :messages="$errors->get('chief_complaint')" class="mt-2" />
                            </div>
                            <div x-data="{ open: false, other: false }" x-on:keydown.escape.stop="open = false" x-on:click.outside="open = false">
                                <label for="symptom-search" class="patient-label">Symptoms</label>
                                <div class="mb-2 flex flex-wrap gap-2" aria-live="polite">
                                    @foreach ($this->selectedSymptoms as $symptom)
                                        <span wire:key="selected-symptom-{{ $symptom->id }}" class="inline-flex items-center gap-2 rounded-full bg-brand-blue px-3 py-1 text-xs">{{ $symptom->name }}<button type="button" wire:click="removeSymptom({{ $symptom->id }})" aria-label="Remove {{ $symptom->name }}" class="rounded p-1">&times;</button></span>
                                    @endforeach
                                    @foreach ($customSymptoms as $index => $name)
                                        @if (is_string($name))
                                        <span wire:key="custom-symptom-{{ $index }}-{{ md5($name) }}" class="inline-flex items-center gap-2 rounded-full bg-brand-pink px-3 py-1 text-xs">{{ $name }}<button type="button" wire:click="removeCustomSymptom({{ $index }})" aria-label="Remove {{ $name }}" class="rounded p-1">&times;</button></span>
                                        @endif
                                    @endforeach
                                </div>
                                <input id="symptom-search" type="search" wire:model.live.debounce.250ms="symptomSearch" x-on:focus="open = true" x-on:input="open = true" x-on:keydown.arrow-down.prevent="open = true; $nextTick(() => $refs.symptomOptions.querySelector('button')?.focus())" x-bind:aria-expanded="open" aria-controls="symptom-options" placeholder="Search and select symptoms..." autocomplete="off" maxlength="255" class="patient-input w-full">
                                <div x-cloak x-show="open" x-ref="symptomOptions" id="symptom-options" class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-line bg-white p-1 shadow-sm">
                                    @forelse ($this->availableSymptoms as $symptom)
                                        <button type="button" wire:key="symptom-option-{{ $symptom->id }}" wire:click="selectSymptom({{ $symptom->id }})" wire:loading.attr="disabled" class="block w-full rounded px-3 py-2 text-left text-xs hover:bg-blush">{{ $symptom->name }}</button>
                                    @empty
                                        <p class="px-3 py-2 text-xs text-muted">No matching symptoms.</p>
                                    @endforelse
                                    <button type="button" x-on:click="other = true; open = false; $nextTick(() => $refs.customSymptom.focus())" class="block w-full rounded px-3 py-2 text-left text-xs font-semibold hover:bg-blush">Other / Add symptom</button>
                                </div>
                                <div x-cloak x-show="other" class="mt-3">
                                    <label for="custom-symptom" class="patient-label">Additional symptom</label>
                                    <div class="flex gap-2"><input x-ref="customSymptom" id="custom-symptom" wire:model="customSymptom" x-on:keydown.enter.prevent="$wire.addCustomSymptom()" maxlength="255" class="patient-input min-w-0 flex-1"><button type="button" wire:click="addCustomSymptom" wire:loading.attr="disabled" class="patient-button text-xs">Add</button></div>
                                    <x-input-error :messages="$errors->get('customSymptom')" class="mt-2" />
                                </div>
                                <x-input-error :messages="$errors->get('selectedSymptomIds')" class="mt-2" />
                                <x-input-error :messages="collect($errors->get('selectedSymptomIds.*'))->flatten()->all()" class="mt-2" />
                                <x-input-error :messages="$errors->get('customSymptoms')" class="mt-2" />
                                <x-input-error :messages="collect($errors->get('customSymptoms.*'))->flatten()->all()" class="mt-2" />
                            </div>
                            <div x-data="{ open: false }" x-on:keydown.escape.stop="open = false" x-on:click.outside="open = false">
                                <label for="diagnosis-search" class="patient-label">Diagnosis <span aria-hidden="true">*</span></label>
                                <p class="mb-2 text-xs">{{ $diagnosisSelection === 'other' ? 'Other / New Diagnosis' : $diagnosis }}</p>
                                <input id="diagnosis-search" type="search" wire:model.live.debounce.250ms="diagnosisSearch" x-on:focus="open = true" x-on:input="open = true" x-on:keydown.arrow-down.prevent="open = true; $nextTick(() => $refs.diagnosisOptions.querySelector('button')?.focus())" x-bind:aria-expanded="open" aria-controls="diagnosis-options" placeholder="Search diagnoses by name..." autocomplete="off" maxlength="255" class="patient-input w-full">
                                <div x-cloak x-show="open" x-ref="diagnosisOptions" id="diagnosis-options" class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-line bg-white p-1 shadow-sm">
                                    @forelse ($this->availableDiagnoses as $option)
                                        <button type="button" wire:key="diagnosis-option-{{ $option->id }}" wire:click="selectDiagnosis('{{ $option->id }}')" x-on:click="open = false" wire:loading.attr="disabled" class="block w-full rounded px-3 py-2 text-left text-xs hover:bg-blush">{{ $option->name }}</button>
                                    @empty
                                        <p class="px-3 py-2 text-xs text-muted">No matching diagnoses.</p>
                                    @endforelse
                                    <button type="button" wire:click="selectDiagnosis('other')" x-on:click="open = false" wire:loading.attr="disabled" class="block w-full rounded px-3 py-2 text-left text-xs font-semibold hover:bg-blush">Other / New Diagnosis</button>
                                </div>
                                @if ($diagnosisSelection === 'other')
                                    <label for="diagnosis" class="patient-label mt-3">New diagnosis wording *</label>
                                    <input id="diagnosis" wire:model="diagnosis" required maxlength="255" class="patient-input w-full">
                                    <p class="mt-1 text-[11px] text-muted">Your wording is saved with this consultation. New catalog entries require review.</p>
                                @endif
                                <x-input-error :messages="$errors->get('diagnosisSelection')" class="mt-2" />
                                <x-input-error :messages="$errors->get('diagnosis')" class="mt-2" />
                            </div>
                            <div>
                                <label for="treatment" class="patient-label">Treatment Plan / Care plan <span aria-hidden="true">*</span></label>
                                <textarea id="treatment" wire:model="treatment" rows="3" required class="patient-input w-full"></textarea>
                                <x-input-error :messages="$errors->get('treatment')" class="mt-2" />
                            </div>
                            <div>
                                <label for="prescription" class="patient-label">Prescription (optional)</label>
                                <textarea id="prescription" wire:model="prescription" rows="2" class="patient-input w-full"></textarea>
                                <x-input-error :messages="$errors->get('prescription')" class="mt-2" />
                            </div>
                            <div>
                                <label for="laboratory_requests" class="patient-label">Laboratory Requests (optional)</label>
                                <textarea id="laboratory_requests" wire:model="laboratory_requests" rows="2" maxlength="10000" class="patient-input w-full"></textarea>
                                <x-input-error :messages="$errors->get('laboratory_requests')" class="mt-2" />
                            </div>
                            <div>
                                <label for="consultation_notes" class="patient-label">Interview &amp; Consultation Notes *</label>
                                <textarea id="consultation_notes" wire:model="consultation_notes" rows="4" required class="patient-input w-full"></textarea>
                                <x-input-error :messages="$errors->get('consultation_notes')" class="mt-2" />
                            </div>
                            <div class="sm:max-w-xs">
                                <label for="consultation_date" class="patient-label">Consultation Date <span aria-hidden="true">*</span></label>
                                <input id="consultation_date" type="date" wire:model="consultation_date" required class="patient-input w-full">
                                <x-input-error :messages="$errors->get('consultation_date')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="patient-card" aria-labelledby="additional-care-title">
                        <h2 id="additional-care-title" class="text-sm font-semibold">Additional care documentation</h2>
                        <p id="additional-care-help" class="mt-1 text-xs leading-relaxed text-muted">Record laboratory requests above. Vaccination notes are not available in this form yet.</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><label for="consultation-vaccination" class="patient-label">Vaccination notes</label><input id="consultation-vaccination" type="text" disabled placeholder="Not available" aria-describedby="additional-care-help" class="patient-input w-full cursor-not-allowed bg-gray-50"></div>
                        </div>
                        <div x-data="{ needsFollowUp: @js(! empty($follow_up_date)) }" class="mt-5 border-t border-line pt-4">
                            <div class="flex items-start justify-between gap-4">
                                <div><label for="consultation-follow-up" class="text-xs font-semibold">Follow-up needed</label><p class="mt-1 text-[11px] text-muted">Schedule the patient's next review after completion.</p></div>
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg bg-brand-blue px-3 py-2 text-xs font-medium">
                                    <input id="consultation-follow-up" type="checkbox" x-model="needsFollowUp" x-on:change="if (!needsFollowUp) $wire.set('follow_up_date', '', false)" class="rounded border-line text-ink focus:ring-brand-blue">
                                    <span x-text="needsFollowUp ? 'Yes' : 'No'">No</span>
                                </label>
                            </div>
                            <div x-cloak x-show="needsFollowUp" class="mt-4 sm:max-w-xs">
                                <label for="follow_up_date" class="patient-label">Follow-up Date</label>
                                <input id="follow_up_date" type="date" wire:model="follow_up_date" x-bind:required="needsFollowUp" x-bind:min="$wire.consultation_date" class="patient-input w-full">
                            </div>
                            <x-input-error :messages="$errors->get('follow_up_date')" class="mt-2" />
                        </div>
                    </section>


                </div>

                <aside class="min-w-0 space-y-5" aria-label="Visit vitals and patient history">

                    <section class="patient-card" aria-labelledby="relevant-history-title">
                        <h2 id="relevant-history-title" class="text-sm font-semibold">Relevant history</h2>
                        <p class="mt-3 text-[11px] leading-relaxed text-muted">Allergy information is not recorded in this system.</p>
                        @if ($latestRecord)
                            <p class="mt-4 break-words text-xs font-medium">{{ $latestRecord->consultation_date?->format('d M Y') ?: 'Date not available' }}: {{ $latestRecord->diagnosis ?: 'Diagnosis not recorded' }}</p>
                            <p class="mt-2 line-clamp-3 break-words text-xs leading-relaxed text-muted">{{ $latestRecord->treatment ?: 'No treatment recorded.' }}</p>
                            <button type="button" wire:click="viewMedicalRecord({{ $latestRecord->medical_record_id }})" class="patient-button mt-4 px-3 py-2 text-xs">View Details</button>
                        @else
                            <p class="mt-4 text-xs text-muted">No previous medical records.</p>
                        @endif
                        <button type="button" x-on:click="historyOpen = !historyOpen; if (historyOpen) $nextTick(() => $refs.historySection.scrollIntoView({ block: 'nearest' }))" x-bind:aria-expanded="historyOpen" aria-controls="consultation-history" class="patient-button mt-3 px-3 py-2 text-xs">Open full history</button>
                    </section>
                    <section class="flex gap-3 rounded-lg border border-line bg-blush/80 px-4 py-4 text-xs">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
                        <div><h3 class="font-semibold">Review before finalizing</h3><p class="mt-2 text-[11px] leading-relaxed text-muted">Completing saves this medical record and updates the queue to Completed. Confirm all entries before saving.</p></div>
                    </section>
                </aside>
            </div>

            <footer class="mt-5 flex flex-wrap items-center justify-between gap-4">
                <p id="consultation-draft-help" class="max-w-md text-[11px] leading-relaxed text-muted">Completing this consultation updates the queue to Completed. Draft saving is not available yet.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" disabled aria-describedby="consultation-draft-help" class="patient-button cursor-not-allowed bg-white text-xs">Save draft</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveMedicalRecord" aria-label="Save Medical Record and Complete Consultation" class="patient-button border-transparent bg-brand-pink text-xs hover:bg-[#f598ba] disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="saveMedicalRecord">Save &amp; complete consultation</span>
                        <span wire:loading wire:target="saveMedicalRecord">Saving consultation...</span>
                    </button>
                </div>
            </footer>
        </form>
    @else
        <section class="patient-card relative py-12 text-center"><h2 class="text-sm font-semibold">No consultation selected</h2><p class="mt-2 text-xs text-muted">Select a patient from the queue to start consultation.</p></section>
    @endif

    @if ($showMedicalRecordModal && $this->selectedMedicalRecord)
        <x-medical-record-details :record="$this->selectedMedicalRecord" />
    @endif
</div>
