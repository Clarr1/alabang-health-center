<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Patient Information and Vital Signs</h1>
        <p class="mt-2 text-sm text-gray-600">Register a patient and record their vital signs in one form.</p>
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

    <form wire:submit.prevent="save" class="space-y-8">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-5 border-b border-gray-200 pb-3">
                <h2 class="text-lg font-semibold text-gray-800">Patient Information</h2>
            </div>

            <div class="grid gap-5 md:grid-cols-2">

                <div>
                    <label for="registration_date" class="mb-1 block text-sm font-medium text-gray-700">Registration Date</label>
                    <input id="registration_date" type="date" wire:model.defer="registration_date" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="first_name" class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                    <input id="first_name" type="text" wire:model.defer="first_name" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="last_name" class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                    <input id="last_name" type="text" wire:model.defer="last_name" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="middle_name" class="mb-1 block text-sm font-medium text-gray-700">Middle Name</label>
                    <input id="middle_name" type="text" wire:model.defer="middle_name" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="sex" class="mb-1 block text-sm font-medium text-gray-700">Sex</label>
                    <select id="sex" wire:model.defer="sex" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <div>
                    <label for="birthdate" class="mb-1 block text-sm font-medium text-gray-700">Birthdate</label>
                    <input id="birthdate" type="date" wire:model.defer="birthdate" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="age" class="mb-1 block text-sm font-medium text-gray-700">Age</label>
                    <input id="age" type="number" min="0" max="150" wire:model.defer="age" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div class="md:col-span-2">
                    <label for="address" class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                    <textarea id="address" wire:model.defer="address" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                </div>

                <div>
                    <label for="contact_number" class="mb-1 block text-sm font-medium text-gray-700">Contact Number</label>
                    <input id="contact_number" type="text" wire:model.defer="contact_number" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="emergency_contact" class="mb-1 block text-sm font-medium text-gray-700">Emergency Contact</label>
                    <input id="emergency_contact" type="text" wire:model.defer="emergency_contact" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                    <select id="status" wire:model.defer="status" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Follow-up">Follow-up</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-5 border-b border-gray-200 pb-3">
                <h2 class="text-lg font-semibold text-gray-800">Vital Signs</h2>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="blood_pressure" class="mb-1 block text-sm font-medium text-gray-700">Blood Pressure</label>
                    <input id="blood_pressure" type="text" wire:model.defer="blood_pressure" placeholder="e.g. 120/80" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="temperature" class="mb-1 block text-sm font-medium text-gray-700">Temperature</label>
                    <input id="temperature" type="number" step="0.1" min="25" max="45" wire:model.defer="temperature" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="pulse_rate" class="mb-1 block text-sm font-medium text-gray-700">Pulse Rate</label>
                    <input id="pulse_rate" type="number" min="0" max="220" wire:model.defer="pulse_rate" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="respiratory_rate" class="mb-1 block text-sm font-medium text-gray-700">Respiratory Rate</label>
                    <input id="respiratory_rate" type="number" min="0" max="80" wire:model.defer="respiratory_rate" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="height" class="mb-1 block text-sm font-medium text-gray-700">Height (cm)</label>
                    <input id="height" type="number" step="0.01" min="20" max="300" wire:model.live="height" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="weight" class="mb-1 block text-sm font-medium text-gray-700">Weight (kg)</label>
                    <input id="weight" type="number" step="0.01" min="1" max="500" wire:model.live="weight" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div>
                    <label for="bmi" class="mb-1 block text-sm font-medium text-gray-700">BMI</label>
                    <input id="bmi" type="number" step="0.1" wire:model.defer="bmi" class="w-full rounded-md border border-gray-300 px-3 py-2 bg-gray-50 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" readonly>
                </div>

                <div>
                    <label for="recorded_at" class="mb-1 block text-sm font-medium text-gray-700">Recorded At</label>
                    <input id="recorded_at" type="datetime-local" wire:model.defer="recorded_at" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                Save Patient & Vital Signs
            </button>
        </div>
    </form>
</div>
