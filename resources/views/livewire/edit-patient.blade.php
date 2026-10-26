<div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8" x-data x-on:patient-updated="window.alert('Patient updated successfully.'); Livewire.navigate($event.detail.redirectUrl)">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Edit Patient</h1>
        <p class="mt-2 text-sm text-gray-600">Update information for patient #{{ $patientId }}.</p>
    </div>

    <form wire:submit="saveChanges" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <x-input-error :messages="$errors->get('patient')" role="alert" />
        <x-patient-information-fields :editing="true" />

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('patients.index') }}" wire:navigate class="rounded-md border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="saveChanges" class="rounded-md bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">Save Changes</button>
        </div>
    </form>
</div>
