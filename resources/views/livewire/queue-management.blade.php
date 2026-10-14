<div x-data="{}" x-on:queue-assignment-prepared="if (window.confirm($event.detail.message)) { $wire.addPatient() }" wire:poll.10s class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    @php($queues = $this->queues)
    <div class="mb-8">
        <a href="{{ route('queues.display') }}" target="_blank" rel="noopener noreferrer" class="mb-4 inline-flex rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">Open Queue Display</a>
        <h1 class="text-2xl font-bold text-gray-900">Queue Management</h1>
        <p class="mt-2 text-sm text-gray-600">Manage patient visits from registration to consultation. Times shown in Asia/Manila.</p>
    </div>

    <livewire:queue-dashboard />

    @if (session('success'))
        <div role="status" class="mt-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @can('queue-register')
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <form wire:submit="prepareAssignment" class="space-y-4">
                <div>
                    <label for="queue-patient-search" class="mb-1 block text-sm font-medium text-gray-700">Add an existing patient</label>
                    <input id="queue-patient-search" type="search" wire:model.live.debounce.300ms="patientSearch" placeholder="Patient name or ID" class="w-full rounded-md border-gray-300">
                    <a href="{{ route('patient.information-form') }}" wire:navigate class="mt-3 inline-block text-sm font-semibold text-blue-600">Register a new patient</a>
                    @if (trim($patientSearch) !== '')
                        @forelse ($this->patientResults as $patient)
                            <div wire:key="queue-patient-{{ $patient->patient_id }}" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 p-3">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $patient->last_name }}, {{ $patient->first_name }} {{ $patient->middle_name }}</p>
                                    <p class="text-sm text-gray-500">Patient #{{ $patient->patient_id }} · {{ $patient->age }} years · {{ $patient->sex }} · {{ $patient->contact_number ?: 'No contact number' }}</p>
                                </div>
                                <button type="button" wire:click="selectPatient({{ $patient->patient_id }})" wire:loading.attr="disabled" class="rounded-md border border-blue-600 px-3 py-2 text-sm font-semibold text-blue-700 disabled:opacity-50">Select Patient</button>
                            </div>
                        @empty
                            <p class="mt-3 text-sm text-gray-500">No matching patients found.</p>
                        @endforelse
                    @endif
                </div>

                @if ($this->selectedPatient)
                    <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-700">
                        <p class="font-semibold text-gray-900">{{ $this->selectedPatient->first_name }} {{ $this->selectedPatient->middle_name }} {{ $this->selectedPatient->last_name }}</p>
                        <p class="mt-1">Patient #{{ $this->selectedPatient->patient_id }} · {{ $this->selectedPatient->age }} years · {{ $this->selectedPatient->sex }} · Born {{ $this->selectedPatient->birthdate }}</p>
                        <p class="mt-1">{{ $this->selectedPatient->address }}</p>
                        <p class="mt-1">{{ $this->selectedPatient->contact_number ?: 'No contact number' }}</p>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Select a patient, then enter the queue number you are assigning.</p>
                @endif

                <div class="sm:max-w-xs">
                    <label for="assigned-queue-number" class="mb-1 block text-sm font-medium text-gray-700">Queue Number <span class="text-red-600">*</span></label>
                    <input id="assigned-queue-number" type="number" wire:model="queueNumber" min="1" max="4294967295" step="1" required class="w-full rounded-md border-gray-300" aria-describedby="assigned-number-help">
                    <p id="assigned-number-help" class="mt-1 text-sm text-gray-500">Enter a positive whole number. Numbers already assigned today cannot be reused.</p>
                    <x-input-error :messages="$errors->get('queueNumber')" class="mt-2" />
                </div>

                <div class="rounded-md border border-gray-200 p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Today's assigned queue numbers · {{ now('Asia/Manila')->format('M j, Y') }}</h2>
                    <p class="mt-1 text-xs text-gray-500">Includes Completed, Cancelled, and No Show visits.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($this->assignedNumbers as $assignedQueue)
                            <span wire:key="assigned-number-{{ $assignedQueue->queue_id }}" title="{{ $assignedQueue->status }}" class="rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700">{{ $assignedQueue->queue_number }}</span>
                        @empty
                            <p class="text-sm text-gray-500">No queue numbers have been assigned today.</p>
                        @endforelse
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled" @disabled($selectedPatientId === null) class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Add to Queue</button>
            </form>

        </section>
    @endcan

    <section class="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-5 grid gap-4 md:grid-cols-3">
            <div>
                <label for="queue-search" class="mb-1 block text-sm font-medium text-gray-700">Search queue</label>
                <input id="queue-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Patient name, ID, or queue number" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label for="queue-status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select id="queue-status" wire:model.live="status" class="w-full rounded-md border-gray-300">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Queue::STATUSES as $queueStatus)<option value="{{ $queueStatus }}">{{ $queueStatus }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="queue-date" class="mb-1 block text-sm font-medium text-gray-700">Queue date</label>
                <input id="queue-date" type="date" wire:model.live="queueDate" class="w-full rounded-md border-gray-300">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead><tr class="text-xs uppercase text-gray-500">
                    <th class="px-3 py-3">Queue Number</th><th class="px-3 py-3">Patient Name</th><th class="px-3 py-3">Patient ID</th>
                    <th class="px-3 py-3">Queue Date</th><th class="px-3 py-3">Current Status</th><th class="px-3 py-3">Time Added</th><th class="px-3 py-3">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($queues as $queue)
                        <tr wire:key="queue-{{ $queue->queue_id }}-{{ $queue->status }}">
                            <td class="px-3 py-3 font-bold text-gray-900">{{ $queue->queue_number }}</td>
                            <td class="px-3 py-3">{{ $queue->patient->last_name }}, {{ $queue->patient->first_name }} {{ $queue->patient->middle_name }}</td>
                            <td class="px-3 py-3">{{ $queue->patient_id }}</td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $queue->queue_date->format('M j, Y') }}</td>
                            <td class="px-3 py-3"><span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $queue->badgeClasses() }}">{{ $queue->status }}</span></td>
                            <td class="whitespace-nowrap px-3 py-3">{{ $queue->created_at->timezone('Asia/Manila')->format('g:i A') }}</td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($queue->actions() as $nextStatus => $label)
                                        @can('queue-transition', $nextStatus)
                                            @if ($nextStatus === 'Ready for Consultation')
                                                <a href="{{ route('queues.vital-signs', ['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id]) }}" wire:navigate class="whitespace-nowrap rounded-md bg-blue-600 px-3 py-2 font-semibold text-white">Record Vital Signs</a>
                                            @elseif ($nextStatus === 'Completed')
                                                <a href="{{ route('queues.consultation', ['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id]) }}" wire:navigate class="whitespace-nowrap rounded-md bg-blue-600 px-3 py-2 font-semibold text-white">Complete Consultation</a>
                                            @else
                                                <button type="button" wire:click="changeStatus({{ $queue->queue_id }}, '{{ $queue->status }}', '{{ $nextStatus }}')" wire:confirm="{{ $label }} for queue #{{ $queue->queue_number }}?" wire:loading.attr="disabled" class="whitespace-nowrap rounded-md border border-gray-300 px-3 py-2 font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50">{{ $label }}</button>
                                            @endif
                                        @endcan
                                    @endforeach
                                    @if (in_array($queue->status, \App\Models\Queue::TERMINAL_STATUSES, true))<span class="text-gray-500">Visit closed</span>@endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No patients are queued for this date or match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $queues->links() }}</div>
    </section>
</div>
