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
        <x-patient-information-fields />

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                Save Patient Information
            </button>
        </div>
    </form>
</div>
