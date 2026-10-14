<div x-data x-on:vital-signs-patient-selected.window="$nextTick(() => $refs.vitalSignsForm.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    @if ($queueId)
        <div class="mb-6 rounded-md bg-blue-50 p-4 text-sm text-blue-700">Recording vital signs for this queue visit. Saving will mark the patient Ready for Consultation. <a href="{{ route('queues.index') }}" wire:navigate class="font-semibold underline">Back to Queue</a></div>
    @endif
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Vital Signs</h1>
        <p class="mt-2 text-sm text-gray-600">Record the vital signs for patient #{{ $patient_id }}.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" wire:poll.5s>
        <h2 class="text-lg font-semibold text-gray-900">Today's Vital Signs Queue</h2>
        <p class="mt-1 text-sm text-gray-600">Select a patient to call them and start recording vital signs.</p>
        <div class="mt-4 space-y-3">
            @forelse ($this->vitalSignsQueue as $entry)
                <div wire:key="vital-queue-{{ $entry->queue_id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 p-3">
                    <button type="button" wire:click="selectQueue({{ $entry->queue_id }}, '{{ $entry->status }}')" wire:loading.attr="disabled" wire:target="selectQueue,saveVitalSigns" class="text-left hover:text-blue-700">
                        <span class="font-semibold">#{{ $entry->queue_number }} - {{ $entry->patient->first_name }} {{ $entry->patient->middle_name }} {{ $entry->patient->last_name }}</span>
                    </button>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $entry->badgeClasses() }}">{{ $entry->status }}</span>
                    <button type="button" wire:click="selectQueue({{ $entry->queue_id }}, '{{ $entry->status }}')" wire:loading.attr="disabled" wire:target="selectQueue,saveVitalSigns" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">Record Vital Signs</button>
                </div>
            @empty
                <p class="text-sm text-gray-500">No patients waiting for vital signs today.</p>
            @endforelse
        </div>
    </section>

    <form wire:submit.prevent="saveVitalSigns" x-ref="vitalSignsForm" wire:confirm="{{ $queueId ? 'Save these vital signs and send this queue visit to consultation?' : 'Save these vital signs?' }}" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="border-b border-gray-200 pb-6">
            @if (! $queueId)
                <label for="patient-search" class="mb-1 block text-sm font-medium text-gray-700">Search Patient</label>
                <input id="patient-search" type="search" wire:model.live.debounce.300ms="patientSearch" placeholder="Search by name or patient ID" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">

                @if ($patientSearch && $this->patientResults->isNotEmpty())
                    <div class="mt-2 overflow-hidden rounded-md border border-gray-200">
                        @foreach ($this->patientResults as $result)
                            <button type="button" wire:click="selectPatient({{ $result->patient_id }})" class="block w-full border-b border-gray-100 px-3 py-2 text-left last:border-b-0 hover:bg-gray-50">
                                <span class="block text-sm font-medium text-gray-900">{{ $result->last_name }}, {{ $result->first_name }} {{ $result->middle_name }}</span>
                                <span class="block text-xs text-gray-500">Patient #{{ $result->patient_id }}</span>
                            </button>
                        @endforeach
                    </div>
                @elseif ($patientSearch && $patientSearch !== $patient?->first_name)
                    <p class="mt-2 text-sm text-gray-500">No matching patients found.</p>
                @endif

            @endif

            @if ($patient)
                <div class="mt-4 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    <p class="font-semibold text-gray-900">{{ $patient->first_name }} {{ $patient->middle_name }} {{ $patient->last_name }}</p>
                    <p class="mt-1">Patient #{{ $patient->patient_id }} · {{ $patient->sex }} · {{ $patient->birthdate }}</p>
                    <p class="mt-1">{{ $patient->contact_number ?: 'No contact number' }}</p>
                </div>
            @else
                <p class="mt-2 text-sm text-gray-500">Select a patient before recording vital signs.</p>
            @endif
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Blood Pressure</label>
                <input type="text" wire:model.defer="blood_pressure" placeholder="e.g. 120/80" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Temperature</label>
                <input type="number" step="0.1" wire:model.defer="temperature" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Pulse Rate</label>
                <input type="number" wire:model.defer="pulse_rate" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Respiratory Rate</label>
                <input type="number" wire:model.defer="respiratory_rate" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Height (cm)</label>
                <input type="number" step="0.01" wire:model.live="height" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Weight (kg)</label>
                <input type="number" step="0.01" wire:model.live="weight" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">BMI</label>
                <div class="flex items-center gap-2">
                    <div class="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900">
                        {{ $bmi !== '' ? $bmi : '—' }}
                    </div>
                    <span class="min-w-[100px] rounded-md border border-gray-200 bg-gray-100 px-2 py-2 text-center text-xs font-semibold text-gray-700">
                        {{ $this->bmiCategory }}
                    </span>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Recorded At</label>
                <input type="date" value="{{ $recorded_at }}" readonly class="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="saveVitalSigns,selectQueue" @disabled(! $patient) class="rounded-md bg-green-600 px-5 py-3 text-sm font-semibold text-white hover:bg-green-500">
                {{ $queueId ? 'Save Vital Signs and Send to Consultation' : 'Save Vital Signs' }}
            </button>
        </div>
    </form>
</div>

@if (session('patient_information_saved'))
    @script
        <script>
            alert('Patient information saved successfully!');
        </script>
    @endscript
@endif
