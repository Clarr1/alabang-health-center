<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Patient Information</h1>
        <p class="mt-2 text-sm text-gray-600">Fill out the patient details first.</p>
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

    <form wire:submit.prevent="savePatient" wire:confirm="Save this patient information?" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="grid gap-5 md:grid-cols-2">

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Registration Date</label>
                <input type="date" wire:model.defer="registration_date" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                <input type="text" wire:model.defer="first_name" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                <input type="text" wire:model.defer="last_name" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Middle Name</label>
                <input type="text" wire:model.defer="middle_name" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Sex</label>
                <select wire:model.defer="sex" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Birthdate</label>
                <input type="date" wire:model.defer="birthdate" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Age</label>
                <input type="number" min="0" max="150" wire:model.defer="age" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                <textarea wire:model.defer="address" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Contact Number</label>
                <input type="text" wire:model.defer="contact_number" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Emergency Contact</label>
                <input type="text" wire:model.defer="emergency_contact" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select wire:model.defer="status" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Follow-up">Follow-up</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                Save Patient Information
            </button>
        </div>
    </form>
</div>

@script
    <script>
        $wire.on('patient-information-saved', ({ redirectUrl }) => {
            alert('Patient registered successfully. Assign their queue number on the next page.');
            $wire.call('resetForm').then(() => Livewire.navigate(redirectUrl));
        });
    </script>
@endscript
