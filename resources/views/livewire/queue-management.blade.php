<div x-data="{ checkInOpen: @js($selectedPatientId !== null || trim($patientSearch) !== '') }" wire:poll.10s class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    @php($queues = $this->queues)
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    <div class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Queue management</h1>
            <p class="mt-2 text-sm text-muted">Coordinate assessment and consultation without losing a patient's place.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('queues.display') }}" target="_blank" rel="noopener noreferrer" class="patient-button bg-white text-xs">Open Queue Display</a>
            @can('queue-register')
                <button x-ref="checkInToggle" type="button" x-on:click="checkInOpen = !checkInOpen; if (checkInOpen) $nextTick(() => $refs.checkInSection.querySelector('#queue-patient-search').focus())" x-bind:aria-expanded="checkInOpen" aria-controls="queue-check-in" class="patient-button border-transparent bg-brand-blue hover:bg-[#90cfe9]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M19 10v8m-4-4h8"/></svg>
                    Check in patient
                </button>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div role="status" class="relative mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="relative mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @can('queue-register')
        <section x-cloak x-show="checkInOpen" x-ref="checkInSection" id="queue-check-in" class="patient-card relative mb-6" aria-labelledby="check-in-title">
            <div class="mb-5 flex items-center justify-between gap-3"><h2 id="check-in-title" class="text-base font-semibold">Check in patient</h2><button type="button" x-on:click="checkInOpen = false; $refs.checkInToggle.focus()" class="patient-button px-3 py-2 text-xs">Close check-in</button></div>
            <form wire:submit="addPatient" class="space-y-4">
                <div>
                    <label for="queue-patient-search" class="patient-label">Add an existing patient</label>
                    <input id="queue-patient-search" x-on:keydown.enter.prevent type="search" wire:model.live.debounce.300ms="patientSearch" placeholder="Patient name, Patient ID or Family ID" class="patient-input w-full">
                    <a href="{{ route('patient.information-form') }}" wire:navigate class="mt-3 inline-block text-sm font-semibold text-ink underline decoration-brand-pink underline-offset-4">Register a new patient</a>
                    @if (trim($patientSearch) !== '')
                        @if ($this->familyResults->isNotEmpty())
                            <div class="mt-4">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Family search results</p>
                                @foreach ($this->familyResults as $family)
                                    <div wire:key="queue-family-{{ $family->id }}" class="mb-3 overflow-x-auto rounded-md border border-line">
                                        <div class="border-b border-line bg-blush px-3 py-2">
                                            <p class="font-semibold text-ink">{{ $family->label }}</p>
                                            <p class="text-xs text-muted">Family ID: {{ $family->family_number }} · {{ $family->members->count() }} registered {{ $family->members->count() === 1 ? 'member' : 'members' }}</p>
                                        </div>
                                        <table class="w-full min-w-[650px] text-left text-sm">
                                            <thead class="text-xs text-muted">
                                                <tr>
                                                    <th scope="col" class="px-3 py-2">Family member</th>
                                                    <th scope="col" class="px-3 py-2">Patient ID</th>
                                                    <th scope="col" class="px-3 py-2">Details</th>
                                                    <th scope="col" class="px-3 py-2 text-right">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-line">
                                                @foreach ($family->members as $member)
                                                    <tr wire:key="queue-family-member-{{ $member->patient_id }}">
                                                        <td class="px-3 py-3 font-medium">{{ $member->last_name }}, {{ $member->first_name }} {{ $member->middle_name }}</td>
                                                        <td class="px-3 py-3">#{{ $member->patient_id }}</td>
                                                        <td class="px-3 py-3 text-xs text-muted">{{ $member->age }} years · {{ $member->sex ?: 'Sex not recorded' }} · {{ $member->household_relationship ?: 'Relationship not recorded' }} · {{ $member->contact_number ?: 'No contact number' }}</td>
                                                        <td class="px-3 py-3 text-right">
                                                            <button type="button" wire:click="selectPatient({{ $member->patient_id }})" wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs">Select / Assign to Queue</button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <p class="mt-4 mb-2 text-xs font-semibold uppercase tracking-wide text-muted">Individual patient search results</p>
                        @forelse ($this->patientResults as $patient)
                            <div wire:key="queue-patient-{{ $patient->patient_id }}" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border border-line p-3">
                                <div>
                                    <p class="font-medium text-ink">{{ $patient->last_name }}, {{ $patient->first_name }} {{ $patient->middle_name }}</p>
                                    <p class="text-sm text-muted">Patient #{{ $patient->patient_id }} · Family ID: {{ $patient->household?->family_number ?: 'Not assigned' }} · {{ $patient->age }} years · {{ $patient->sex }} · {{ $patient->contact_number ?: 'No contact number' }}</p>
                                </div>
                                <button type="button" wire:click="selectPatient({{ $patient->patient_id }})" wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs">Select Patient</button>
                            </div>
                        @empty
                            @if ($this->familyResults->isEmpty())
                                <p class="mt-3 text-sm text-muted">No matching patients or families found. Check the Patient ID, name, or Family ID and try again.</p>
                            @else
                                <p class="mt-3 text-sm text-muted">No individual patient matches found.</p>
                            @endif
                        @endforelse
                    @endif
                </div>

                @if ($this->selectedPatient)
                    <div class="rounded-md bg-blush p-4 text-sm text-ink">
                        <p class="font-semibold text-ink">{{ $this->selectedPatient->first_name }} {{ $this->selectedPatient->middle_name }} {{ $this->selectedPatient->last_name }}</p>
                        <p class="mt-1">Patient #{{ $this->selectedPatient->patient_id }} · {{ $this->selectedPatient->age }} years · {{ $this->selectedPatient->sex }} · Born {{ $this->selectedPatient->birthdate }}</p>
                        <p class="mt-1">{{ $this->selectedPatient->address }}</p>
                        <p class="mt-1">{{ $this->selectedPatient->contact_number ?: 'No contact number' }}</p>
                    </div>
                @else
                    <p class="text-sm text-muted">Select a patient, then add them to the queue.</p>
                @endif

                <p class="text-sm text-muted">The next queue number is assigned automatically when the patient is added. Numbering starts at 001 each day.</p>

                <div class="rounded-md border border-line p-4">
                    <h2 class="text-sm font-semibold text-ink">Today's assigned queue numbers · {{ now('Asia/Manila')->format('M j, Y') }}</h2>
                    <p class="mt-1 text-xs text-muted">Includes Completed, Cancelled, and No Show visits.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($this->assignedNumbers as $assignedQueue)
                            <span wire:key="assigned-number-{{ $assignedQueue->queue_id }}" title="{{ $assignedQueue->status }}" class="rounded-full bg-blush px-3 py-1 text-sm font-semibold text-ink">{{ $assignedQueue->formattedQueueNumber() }}</span>
                        @empty
                            <p class="text-sm text-muted">No queue numbers have been assigned today.</p>
                        @endforelse
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled" @disabled($selectedPatientId === null) class="patient-button bg-brand-pink disabled:cursor-not-allowed">Add to Queue</button>
            </form>

        </section>
    @endcan

    <div class="relative mb-5"><livewire:queue-dashboard /></div>

    <section class="patient-card relative" aria-labelledby="queue-directory-title">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="queue-directory-title" class="text-base font-semibold">{{ $queueDate === now('Asia/Manila')->toDateString() ? "Today's queue" : 'Queue history' }}</h2>
                <p class="mt-1 text-xs text-muted">{{ number_format($queues->total()) }} matching {{ $queues->total() === 1 ? 'visit' : 'visits' }} · {{ $queueDate }} · Times in Asia/Manila</p>
            </div>
            <span wire:loading.delay role="status" class="text-xs text-muted">Updating queue…</span>
        </div>
        <div class="mb-5 flex gap-2 rounded-lg border border-line bg-blush px-3 py-3 text-[11px] leading-relaxed text-muted">
            <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
            <p>Queue numbers follow the existing workflow. Priority tagging and station/room assignments are not available yet.</p>
        </div>
        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="queue-status" class="patient-label">Stage / Status</label>
                <select id="queue-status" wire:model.live="status" class="patient-input w-full">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Queue::STATUSES as $queueStatus)<option value="{{ $queueStatus }}">{{ $queueStatus }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="queue-date" class="patient-label">Queue date</label>
                <input id="queue-date" type="date" wire:model.live="queueDate" class="patient-input w-full">
            </div>
            <div>
                <label for="queue-priority" class="patient-label">Priority</label>
                <select id="queue-priority" disabled aria-describedby="queue-priority-help" class="patient-input w-full cursor-not-allowed bg-gray-50 text-muted">
                    <option>Not available</option>
                </select>
                <span id="queue-priority-help" class="sr-only">Priority tagging has not been implemented.</span>
            </div>
            <div>
                <label for="queue-search" class="patient-label">Search queue / patient</label>
                <input id="queue-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Queue number, name or ID…" class="patient-input w-full">
            </div>
        </div>
        <div wire:loading.class="opacity-50" wire:target="search,status,queueDate,gotoPage,nextPage,previousPage,changeStatus">
            <div class="overflow-x-auto rounded-t-md" tabindex="0" role="region" aria-label="Queue table, scroll horizontally for all columns">
                <table class="w-full min-w-[850px] text-left text-xs">
                    <thead class="bg-blush text-[11px]">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Queue</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Patient</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Check-in</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Priority</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Stage / Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line border-b border-line">
                        @forelse ($queues as $queue)
                            <tr wire:key="queue-{{ $queue->queue_id }}-{{ $queue->status }}" class="transition-colors hover:bg-blush/60">
                                <td class="px-3 py-4 text-base font-semibold tabular-nums">{{ $queue->formattedQueueNumber() }}</td>
                                <td class="px-3 py-4">
                                    <span class="block font-medium">{{ trim(implode(' ', array_filter([$queue->patient?->first_name, $queue->patient?->middle_name, $queue->patient?->last_name]))) ?: 'Patient unavailable' }}</span>
                                    <span class="mt-1 block text-[11px] text-muted">Patient #{{ $queue->patient_id }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $queue->created_at->timezone('Asia/Manila')->format('h:i A') }}</td>
                                <td class="px-3 py-4 text-[11px] text-muted">Not recorded</td>
                                <td class="px-3 py-4">
                                    <span @class(['inline-block whitespace-nowrap rounded px-2 py-1 text-[11px] font-medium',
                                        'bg-brand-pink' => $queue->status === 'In Consultation',
                                        'bg-brand-blue' => in_array($queue->status, ['Called', 'Vital Signs', 'Ready for Consultation'], true),
                                        'bg-blush' => $queue->status === 'Waiting',
                                        'bg-[#e9ebf0]' => in_array($queue->status, \App\Models\Queue::TERMINAL_STATUSES, true),
                                    ])>{{ $queue->status }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @foreach ($queue->actions() as $nextStatus => $label)
                                            @can('queue-transition', $nextStatus)
                                                @if ($nextStatus === 'Ready for Consultation')
                                                    <a href="{{ route('queues.vital-signs', ['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id]) }}" wire:navigate class="patient-button whitespace-nowrap px-3 py-2 text-xs">Record Vital Signs</a>
                                                @elseif ($nextStatus === 'Completed')
                                                    <a href="{{ route('queues.consultation', ['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id]) }}" wire:navigate class="patient-button whitespace-nowrap px-3 py-2 text-xs">Complete Consultation</a>
                                                @else
                                                    <button type="button" wire:click="changeStatus({{ $queue->queue_id }}, '{{ $queue->status }}', '{{ $nextStatus }}')" wire:confirm="{{ $label }} for queue #{{ $queue->formattedQueueNumber() }}?" wire:loading.attr="disabled" class="patient-button whitespace-nowrap px-3 py-2 text-xs">{{ $label }}</button>
                                                @endif
                                            @endcan
                                        @endforeach
                                        @if (in_array($queue->status, \App\Models\Queue::TERMINAL_STATUSES, true))
                                            @if ($queue->medicalRecord)
                                                @can('medical-records-view')
                                                <a href="{{ route('medical-records.show', ['record' => $queue->patient_id]) }}" wire:navigate class="patient-button px-3 py-2 text-xs">View visit</a>
                                                @endcan
                                            @endif
                                            <span class="text-[11px] text-muted">Visit closed</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="h-2"></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($queues->isEmpty())
                <div class="border-b border-line px-4 py-10 text-center">
                    <p class="text-sm font-semibold">No patients are queued for this date or match these filters.</p>
                    <p class="mt-2 text-xs text-muted">Try another date or search, or check in an existing patient.</p>
                </div>
            @endif
            <nav class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Queue pagination">
                <p class="text-xs text-muted">Showing {{ $queues->firstItem() ?? 0 }}–{{ $queues->lastItem() ?? 0 }} of {{ number_format($queues->total()) }} visits · Completed visits retained in history</p>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled" @disabled($queues->onFirstPage()) class="patient-button px-3 py-2 text-xs disabled:cursor-not-allowed disabled:bg-[#f1f3f7]">Previous</button>
                    <span aria-current="page" aria-label="Current page {{ $queues->currentPage() }}" class="rounded-lg bg-brand-blue px-3 py-2 text-xs font-semibold">{{ $queues->currentPage() }}</span>
                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled" @disabled(! $queues->hasMorePages()) class="patient-button px-3 py-2 text-xs disabled:cursor-not-allowed disabled:bg-[#f1f3f7]">Next page</button>
                </div>
            </nav>
        </div>
    </section>
</div>
