<div class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    <div class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Medical Records</h1><p class="mt-2 text-sm text-muted">Browse saved consultations and review patient history.</p></div>
        @can('queue-consult')
            <a href="{{ route('doctors-consultation.index') }}" wire:navigate class="patient-button border-transparent bg-brand-pink hover:bg-[#f598ba]">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                New consultation
            </a>
        @endcan
    </div>

    <section class="patient-card relative mb-5" aria-label="Medical record search and filters">
        <div class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
            <div>
                <label for="medical-record-search" class="patient-label">Search patient</label>
                <input id="medical-record-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Patient name or unique ID" class="patient-input w-full">
            </div>
            <div>
                <label for="records-from-date" class="patient-label">From date</label>
                <input id="records-from-date" type="date" wire:model.live="fromDate" class="patient-input w-full">
            </div>
            <div>
                <label for="records-to-date" class="patient-label">To date</label>
                <input id="records-to-date" type="date" wire:model.live="toDate" class="patient-input w-full">
            </div>
            <button type="button" wire:click="clearFilters" wire:loading.attr="disabled" class="patient-button bg-brand-blue hover:bg-[#90cfe9]">Clear filters</button>
        </div>
        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif
    </section>

    <section class="patient-card relative" aria-labelledby="medical-record-directory-title">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div><h2 id="medical-record-directory-title" class="text-base font-semibold">Patient medical records</h2><p class="mt-1 text-xs text-muted">{{ number_format($patients->total()) }} {{ $patients->total() === 1 ? 'patient' : 'patients' }} &middot; Sorted by latest consultation</p></div>
            <span wire:loading.delay role="status" class="text-xs text-muted">Loading medical records...</span>
        </div>
        <div wire:loading.class="opacity-50" wire:target="search,fromDate,toDate,clearFilters,gotoPage,nextPage,previousPage">
            <div class="overflow-x-auto rounded-t-md" tabindex="0" role="region" aria-label="Medical records table, scroll horizontally for all columns">
                <table class="w-full min-w-[720px] text-left text-xs">
                    <thead class="bg-blush text-[11px]"><tr>
                        <th scope="col" class="px-3 py-3 font-semibold">Patient Name</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Patient ID</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Total Medical Records</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Latest Consultation Date</th>
                        <th scope="col" class="px-3 py-3 text-right font-semibold">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line border-b border-line">
                        @forelse ($patients as $patient)
                            <tr wire:key="patient-medical-records-{{ $patient->patient_id }}" class="transition-colors hover:bg-blush/60">
                                <td class="px-3 py-4"><span class="block font-semibold">{{ trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->last_name]))) ?: 'Not provided.' }}</span><span class="mt-1 block text-[11px] text-muted">{{ $patient->age }} years &middot; {{ $patient->sex }}</span></td>
                                <td class="px-3 py-4">{{ $patient->patient_id }}</td>
                                <td class="px-3 py-4"><span class="rounded bg-[#e9ebf0] px-2 py-1 text-[11px] font-medium">{{ $patient->medical_records_count }}</span></td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $patient->medical_records_max_consultation_date ? \Illuminate\Support\Carbon::parse($patient->medical_records_max_consultation_date)->format('d M Y') : 'Not provided.' }}</td>
                                <td class="px-3 py-3 text-right"><a href="{{ route('medical-records.show', ['record' => $patient->patient_id, 'search' => $search, 'fromDate' => $fromDate, 'toDate' => $toDate, 'page' => $patients->currentPage()]) }}" wire:navigate class="patient-button whitespace-nowrap px-3 py-2 text-xs">View Details</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="h-2"></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($patients->isEmpty())
                <div class="border-b border-line px-4 py-10 text-center"><p class="text-sm font-semibold">No medical records match your filters.</p><p class="mt-2 text-xs text-muted">Try another name, patient ID, or consultation date range.</p></div>
            @endif
            <nav class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Medical records pagination">
                <p class="text-xs text-muted">Showing {{ $patients->firstItem() ?? 0 }}&ndash;{{ $patients->lastItem() ?? 0 }} of {{ number_format($patients->total()) }} patients</p>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled" @disabled($patients->onFirstPage()) class="patient-button px-3 py-2 text-xs disabled:cursor-not-allowed disabled:bg-[#f1f3f7]">Previous</button>
                    <span aria-current="page" aria-label="Current page {{ $patients->currentPage() }}" class="rounded-lg bg-brand-blue px-3 py-2 text-xs font-semibold">{{ $patients->currentPage() }}</span>
                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled" @disabled(! $patients->hasMorePages()) class="patient-button px-3 py-2 text-xs disabled:cursor-not-allowed disabled:bg-[#f1f3f7]">Next page</button>
                </div>
            </nav>
        </div>
        <p class="mt-4 text-[11px] text-muted">Counts reflect the selected consultation dates. Open a patient to review their complete history.</p>
    </section>
</div>
