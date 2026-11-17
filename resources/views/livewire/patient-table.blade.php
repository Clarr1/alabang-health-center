<div wire:poll.60s class="patient-page relative isolate {{ $showPatientModal || $showCardModal ? 'z-50' : '' }} mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 -z-10 w-[440px] rounded-full max-w-[70%] opacity-[0.035]">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <nav aria-label="Breadcrumb" class="mb-2 text-xs text-muted"><ol class="flex flex-wrap items-center gap-2"><li>Patient Management</li><li aria-hidden="true">/</li><li aria-current="page">Patient Record</li></ol></nav>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Patient Record</h1>
            <p class="mt-2 text-sm text-muted">Find an existing patient before creating a new registration.</p>
        </div>
        @can('queue-register')
        <a href="{{ route('patient.information-form') }}" wire:navigate class="patient-button border-transparent bg-brand-pink hover:bg-[#f598ba]">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M19 10v8m-4-4h8"/></svg>
            Register patient
        </a>
        @endcan
    </div>

    <x-patient-record-nav />
    <section class="patient-card mb-6" aria-label="Patient search and filters">
        <form wire:submit="$refresh">
            <label for="patient-table-search" class="patient-label">Search by name or unique patient ID</label>
            <div class="flex flex-wrap gap-3">
                <input id="patient-table-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search patients…" class="patient-input min-w-0 flex-1 basis-48">
                <button type="submit" class="patient-button border-transparent bg-brand-blue hover:bg-[#90cfe9]" wire:loading.attr="disabled">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/></svg>
                    Search
                </button>
                <button type="button" disabled aria-describedby="qr-unavailable" class="patient-button cursor-not-allowed bg-white text-muted">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 8V4a1 1 0 0 1 1-1h4m8 0h4a1 1 0 0 1 1 1v4M3 16v4a1 1 0 0 0 1 1h4m8 0h4a1 1 0 0 0 1-1v-4M7 12h10"/></svg>
                    Scan QR
                </button>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="patient-record-status" class="patient-label">Record status</label>
                    <select id="patient-record-status" wire:model.live="recordStatus" class="patient-input w-full">
                        <option value="">All records</option>
                        @foreach ($this->recordStatuses as $status)
                            <option value="{{ $status }}">{{ $status }} records</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="patient-sex" class="patient-label">Sex</label>
                    <select id="patient-sex" wire:model.live="sex" class="patient-input w-full">
                        <option value="">All</option>
                        <option value="Female">Female</option>
                        <option value="Male">Male</option>
                    </select>
                </div>
                <div>
                    <label for="patient-last-visit" class="patient-label">Last visit</label>
                    <select id="patient-last-visit" wire:model.live="lastVisit" class="patient-input w-full">
                        <option value="">Any date</option>
                        <option value="today">Today</option>
                        <option value="week">Last 7 days</option>
                        <option value="month">Last 30 days</option>
                        <option value="never">No visits yet</option>
                    </select>
                </div>
            </div>
            <p id="qr-unavailable" class="mt-3 text-xs text-muted">QR scanning is not available yet. Search using the ID printed on the patient card.</p>
        </form>
    </section>

    <section class="patient-card" aria-labelledby="patient-directory-title">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="patient-directory-title" class="text-base font-semibold">Patient directory</h2>
                <p class="mt-1 text-xs text-muted">{{ number_format($this->activePatientCount) }} active patient records · Sorted by latest visit</p>
            </div>
            <span wire:loading.delay role="status" class="text-xs text-muted">Loading patients…</span>
        </div>
        <div wire:loading.class="opacity-50" wire:target="search,recordStatus,sex,lastVisit,resetFilters,gotoPage,nextPage,previousPage">
            <div class="overflow-x-auto rounded-t-md" tabindex="0" role="region" aria-label="Patient directory table, scroll horizontally for all columns">
                <table class="w-full min-w-[850px] text-left text-xs">
                    <thead class="bg-blush text-[11px] font-medium">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Patient / Unique ID</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Age / Sex</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Family Number</th>
                            <th scope="col" class="px-3 py-3 font-semibold" >Barangay / Address</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Contact</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Registered</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Last visit</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line border-b border-line">
                        @forelse ($this->patients as $patient)
                            <tr wire:key="patient-{{ $patient->patient_id }}" class="transition-colors hover:bg-blush/60">
                                <td class="px-3 py-4">
                                    <span class="block font-semibold">{{ trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->last_name]))) }}</span>
                                    <span class="mt-1 block text-[11px] text-muted">{{ $patient->patient_id }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $patient->age ?? '—' }} · {{ $patient->sex ?: 'Not available' }}</td>
                                <td class="whitespace-nowrap px-3 py-4">@if ($patient->household)@can('queue-view')<a href="{{ route('families.show', $patient->household) }}" wire:navigate class="underline underline-offset-4">{{ $patient->household->family_number }}</a>@else{{ $patient->household->family_number }}@endcan @else Not assigned @endif</td>
                                <td class="max-w-[180px] px-3 py-4"><span class="line-clamp-2" title="{{ $patient->address }}">{{ $patient->address ?: 'Not available' }}</span></td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $patient->contact_number ?: 'Not provided.' }}</td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $patient->registration_date ? \Illuminate\Support\Carbon::parse($patient->registration_date)->format('d M Y') : 'Not provided.' }}</td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $patient->last_visit_at ? \Illuminate\Support\Carbon::parse($patient->last_visit_at, 'Asia/Manila')->format('d M Y') : 'No visits yet' }}</td>
                                <td class="px-3 py-4"><span class="inline-block rounded bg-[#e9ebf0] px-2 py-1 text-[11px] font-medium">{{ $patient->status ?: 'Not available' }}</span></td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2 whitespace-nowrap">
                                        <button type="button" wire:click="viewPatient({{ $patient->patient_id }})" wire:loading.attr="disabled" class="patient-button px-3 py-2 text-xs">Open record</button>
                                        @can('queue-register')
                                            <a href="{{ route('patients.edit', $patient) }}" wire:navigate class="rounded px-1 py-2 text-xs font-medium hover:text-pink-700 focus-visible:outline-pink-500">Edit</a>
                                        @endcan
                                        <button type="button" wire:click="generateIdCard({{ $patient->patient_id }})" wire:loading.attr="disabled" wire:target="generateIdCard" class="rounded px-1 py-2 text-xs font-medium text-muted hover:text-ink disabled:opacity-50">Generate ID Card</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12 text-center">
                                <p class="text-sm font-semibold">No patients found.</p>
                                <p class="mt-2 text-xs text-muted">Try another name or patient ID, or adjust the filters.</p>
                                @if ($search !== '' || $recordStatus !== '' || $sex !== '' || $lastVisit !== '')
                                    <button type="button" wire:click="resetFilters" class="patient-button mt-4 text-xs">Clear search and filters</button>
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <nav class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Patient pagination">
                <p class="text-xs text-muted">Showing {{ $this->patients->firstItem() ?? 0 }}–{{ $this->patients->lastItem() ?? 0 }} of {{ number_format($this->patients->total()) }} patients</p>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled" @disabled($this->patients->onFirstPage()) class="patient-button px-3 py-2 text-xs disabled:cursor-not-allowed disabled:bg-[#f1f3f7]">Previous</button>
                    @foreach ($this->patients->getUrlRange(max(1, $this->patients->currentPage() - 1), min($this->patients->lastPage(), $this->patients->currentPage() + 1)) as $page => $url)
                        <button type="button" wire:key="patient-page-{{ $page }}" wire:click="gotoPage({{ $page }})" wire:loading.attr="disabled" aria-label="Go to page {{ $page }}"
                                @if ($page === $this->patients->currentPage()) aria-current="page" @endif
                                @class(['patient-button px-3 py-2 text-xs', 'border-brand-blue bg-brand-blue' => $page === $this->patients->currentPage()])>{{ $page }}</button>
                    @endforeach
                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled" @disabled(! $this->patients->hasMorePages()) class="patient-button px-3 py-2 text-xs disabled:cursor-not-allowed disabled:bg-[#f1f3f7]">Next</button>
                </div>
            </nav>
        </div>
    </section>
    <aside class="mt-6 flex gap-3 rounded-lg border border-line bg-blush/70 px-4 py-4 text-xs" aria-label="Identity check">
        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
        <div><p class="font-medium">Identity check</p><p class="mt-1 leading-relaxed text-muted">Confirm the patient's full name and birthdate before opening or updating a record. QR identification does not display clinical information.</p></div>
    </aside>

    @if ($showPatientModal && $this->selectedPatient)
        <div x-data x-init="$nextTick(() => $refs.patientClose.focus())" x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closePatientModal()" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="patient-modal-title">
            <div class="flex min-h-screen items-center justify-center bg-gray-900/50 px-4 py-8">
                <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                        <div>
                            <h2 id="patient-modal-title" class="text-lg font-semibold text-gray-900">Patient Details</h2>
                            <p class="mt-1 text-sm text-gray-500">Patient #{{ $this->selectedPatient->patient_id }}</p>
                        </div>
                        <button x-ref="patientClose" type="button" wire:click="closePatientModal" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>
                    </div>

                    <div class="mt-5 grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Patient Information</h3>
                            <dl class="space-y-1 text-sm text-gray-700">
                                <div><dt class="inline font-medium">Name:</dt> <dd class="inline">{{ trim(implode(' ', array_filter([$this->selectedPatient->first_name, $this->selectedPatient->middle_name, $this->selectedPatient->last_name]))) ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Birthdate:</dt> <dd class="inline">{{ $this->selectedPatient->birthdate ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Age:</dt> <dd class="inline">{{ $this->selectedPatient->age ?? 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Sex:</dt> <dd class="inline">{{ $this->selectedPatient->sex ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Contact number:</dt> <dd class="inline">{{ $this->selectedPatient->contact_number ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Emergency contact:</dt> <dd class="inline">{{ $this->selectedPatient->emergency_contact ?: 'Not available' }}</dd></div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Registration</h3>
                            <dl class="space-y-1 text-sm text-gray-700">
                                <div><dt class="inline font-medium">Address:</dt> <dd class="inline">{{ $this->selectedPatient->address ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Registration date:</dt> <dd class="inline">{{ $this->selectedPatient->registration_date ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Status:</dt> <dd class="inline">{{ $this->selectedPatient->status ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Vital signs recorded:</dt> <dd class="inline">{{ $this->selectedPatient->vitalSigns->count() }}</dd></div>
                                <div><dt class="inline font-medium">Medical records:</dt> <dd class="inline">{{ $this->selectedPatient->medicalRecords->count() }}</dd></div>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-5">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Recent Vital Signs</h3>
                        @forelse ($this->selectedPatient->vitalSigns->sortByDesc('recorded_at')->take(3) as $vitalSign)
                            <div class="border-b border-gray-100 py-2 text-sm text-gray-700 last:border-b-0"><span class="font-medium">{{ $vitalSign->recorded_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?: 'Not available' }}</span> · BP {{ $vitalSign->blood_pressure ?: 'Not available' }} · Temp {{ $vitalSign->temperature ?: 'Not available' }} · Recorded by {{ $vitalSign->recordedBy?->name ?: 'Not available' }}</div>
                        @empty
                            <p class="text-sm text-gray-500">No vital signs recorded.</p>
                        @endforelse
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-5">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Medical Records</h3>
                        @forelse ($this->selectedPatient->medicalRecords->sortByDesc('consultation_date')->take(3) as $medicalRecord)
                            <div class="border-b border-gray-100 py-2 text-sm text-gray-700 last:border-b-0"><span class="font-medium">{{ $medicalRecord->consultation_date?->format('M j, Y') ?: 'Not available' }}</span> · {{ $medicalRecord->diagnosis ?: 'Not available' }} · Handled by {{ $medicalRecord->handledBy?->name ?: 'Not available' }}</div>
                        @empty
                            <p class="text-sm text-gray-500">No medical records found.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if ($showCardModal && $cardPatientId)
        <div wire:key="patient-card-modal-{{ $cardPatientId }}" x-data="{ cardReady: false }" x-init="$nextTick(() => $refs.close.focus())" x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeCardModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/50 px-4 py-8" role="dialog" aria-modal="true" aria-labelledby="card-modal-title" wire:click.self="closeCardModal">
            <section class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 id="card-modal-title" class="text-lg font-semibold text-gray-900">Patient Identification Card</h2>
                    <button x-ref="close" type="button" wire:click="closeCardModal" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>
                </div>
                <div class="overflow-x-auto rounded-lg bg-gray-100 p-4">
                    <iframe x-ref="card" x-on:load="cardReady = true" src="{{ route('patients.card.show', $cardPatientId) }}" title="Patient {{ $cardPatientId }} healthcare card preview" class="mx-auto block border-0 bg-white shadow" style="width: 85.60mm; height: 53.98mm;"></iframe>
                </div>
                <p class="mt-3 text-xs text-gray-500">Card size: 85.60 × 53.98 mm. Print at actual size (100%) with browser headers and footers disabled.</p>
                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <a href="{{ route('patients.card.download', $cardPatientId) }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Download PDF</a>
                    <button type="button" x-bind:disabled="!cardReady" x-on:click="$refs.card.contentWindow.focus(); $refs.card.contentWindow.print()" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50">Print</button>
                </div>
            </section>
        </div>
    @endif
</div>
