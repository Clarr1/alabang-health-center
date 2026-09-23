<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
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

    <form wire:submit.prevent="saveVitalSigns" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
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
                <input type="number" step="0.1" wire:model.defer="bmi" readonly class="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Recorded At</label>
                <input type="datetime-local" wire:model.defer="recorded_at" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-green-600 px-5 py-3 text-sm font-semibold text-white hover:bg-green-500">
                Save Vital Signs
            </button>
        </div>
    </form>
</div>
