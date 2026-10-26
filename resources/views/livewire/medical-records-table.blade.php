<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Medical Records</h1>
            <p class="mt-2 text-sm text-gray-600">Browse saved consultations and review patient history.</p>
        </div>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="sm:col-span-2 xl:col-span-1">
                <label for="medical-record-search" class="mb-1 block text-sm font-medium text-gray-700">Search patient</label>
                <input id="medical-record-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Patient name or ID" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="records-from-date" class="mb-1 block text-sm font-medium text-gray-700">From date</label>
                <input id="records-from-date" type="date" wire:model.live="fromDate" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="records-to-date" class="mb-1 block text-sm font-medium text-gray-700">To date</label>
                <input id="records-to-date" type="date" wire:model.live="toDate" class="w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="clearFilters" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Clear filters</button>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <p class="mb-4 text-sm text-gray-500">{{ $medicalRecords->total() }} {{ $medicalRecords->total() === 1 ? 'record' : 'records' }}</p>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500">
                        <th class="px-3 py-3">Patient Name</th>
                        <th class="px-3 py-3">Patient ID</th>
                        <th class="px-3 py-3">Consultation Date</th>
                        <th class="px-3 py-3">Attending Doctor</th>
                        <th class="px-3 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($medicalRecords as $record)
                        <tr wire:key="medical-record-{{ $record->medical_record_id }}">
                            <td class="px-3 py-3 font-medium text-gray-900">{{ trim(implode(' ', array_filter([$record->patient?->first_name, $record->patient?->middle_name, $record->patient?->last_name]))) ?: 'Not available' }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ $record->patient_id }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-gray-700">{{ $record->consultation_date?->format('M j, Y') ?: 'Not available' }}</td>
                            <td class="px-3 py-3 text-gray-700">{{ $record->handledBy?->name ?: 'Not available' }}</td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('medical-records.show', ['record' => $record->medical_record_id, 'search' => $search, 'fromDate' => $fromDate, 'toDate' => $toDate, 'page' => $medicalRecords->currentPage()]) }}" wire:navigate class="inline-block whitespace-nowrap rounded-md bg-blue-600 px-3 py-2 font-semibold text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">View Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-10 text-center text-gray-500">No medical records match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $medicalRecords->links() }}</div>
    </section>

</div>