<div x-data x-on:patient-updated="window.alert('Patient updated successfully.'); Livewire.navigate($event.detail.redirectUrl)" class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    <div class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Edit Patient</h1><p class="mt-2 text-sm text-muted">Update information for patient #{{ $patientId }}. Fields marked * are required.</p></div>
        <a href="{{ route('patients.index') }}" wire:navigate class="patient-button bg-white text-xs"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg>Back to Patient Record</a>
    </div>
    <ol class="relative mb-6 grid gap-3 text-xs text-muted sm:grid-cols-2" aria-label="Patient update progress">
        <li x-bind:class="! $wire.showReview ? 'font-semibold text-ink' : ''" x-bind:aria-current="! $wire.showReview ? 'step' : null">1 &nbsp; Patient information</li>
        <li x-bind:class="$wire.showReview ? 'font-semibold text-ink' : ''" x-bind:aria-current="$wire.showReview ? 'step' : null">2 &nbsp; Review changes</li>
    </ol>
    @if ($errors->any())
        <div role="alert" class="relative mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form wire:submit="saveChanges" class="space-y-5">
        <x-patient-information-fields :editing="true" :reviewing="$showReview" :details="$this->patientSummary" />
        @php($duplicates = $this->duplicatePatients)
        <aside class="relative flex gap-3 rounded-lg border border-line bg-blush/80 px-4 py-4 text-xs" aria-label="Duplicate patient check">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
            <div>
                @if (! filled($first_name) || ! filled($last_name) || ! filled($birthdate) || $age === '')
                    <p class="font-semibold">Duplicate check</p><p class="mt-1 text-[11px] leading-relaxed text-muted">Enter first name, last name, and birthdate to check other patient records.</p>
                @elseif ($duplicates->isEmpty())
                    <p class="font-semibold">Duplicate check: no other matching record found</p><p class="mt-1 text-[11px] leading-relaxed text-muted">This patient is excluded from the check. No other record shares the first name, last name, and birthdate.</p>
                @else
                    <p class="font-semibold">Possible duplicate patient records</p><p class="mt-1 text-[11px] leading-relaxed text-muted">These other records share the first name, last name, and birthdate. Review them before saving changes.</p>
                    <ul class="mt-2 space-y-2">@foreach ($duplicates as $duplicate)<li wire:key="edit-duplicate-{{ $duplicate->patient_id }}"><a href="{{ route('patients.edit', $duplicate) }}" target="_blank" rel="noopener noreferrer" class="font-medium underline decoration-brand-pink underline-offset-4">Patient #{{ $duplicate->patient_id }} &middot; {{ $duplicate->first_name }} {{ $duplicate->middle_name }} {{ $duplicate->last_name }} &middot; {{ $duplicate->status }}</a></li>@endforeach</ul>
                @endif
            </div>
        </aside>
        <footer class="relative flex flex-wrap items-center justify-between gap-4">
            <p class="text-[11px] text-muted">Confirm the patient's identity and review changes before saving.</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('patients.index') }}" wire:navigate class="patient-button bg-white text-xs">Cancel</a>
                @if ($showReview)
                    <button type="button" wire:click="returnToDetails" wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs hover:bg-[#90cfe9]">Back to details</button>
                @else
                    <button type="button" wire:click="reviewDetails" wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs hover:bg-[#90cfe9]">Review details</button>
                @endif
                <button type="submit" wire:loading.attr="disabled" wire:target="saveChanges,reviewDetails" class="patient-button border-transparent bg-brand-pink text-xs hover:bg-[#f598ba] disabled:cursor-not-allowed"><span wire:loading.remove wire:target="saveChanges">Save Changes</span><span wire:loading wire:target="saveChanges">Saving...</span></button>
            </div>
        </footer>
    </form>
</div>
