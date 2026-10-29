<div class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    @if ($registeredPatientId !== null)
        @php($registered = $this->registeredPatient)
        <div wire:key="registered-patient-{{ $registeredPatientId }}" class="relative max-w-4xl" x-data="{ cardReady: false }">
            <header class="mb-6"><h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Patient registered</h1><p class="mt-2 text-sm text-muted">Healthcare ID created. Keep this ID and bring it on every visit.</p></header>
            <div role="status" class="mb-5 flex gap-3 rounded-lg border border-line bg-blush/80 p-4 text-xs">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
                <div><p class="font-semibold">Patient registered successfully.</p><p class="mt-1 leading-relaxed text-muted">{{ trim(implode(' ', array_filter([$registered->first_name, $registered->middle_name, $registered->last_name]))) }} &middot; Patient ID {{ $registered->patient_id }} &middot; Registered {{ $registered->created_at?->timezone('Asia/Manila')->format('d M Y, h:i A') }}</p></div>
            </div>
            <section class="patient-card" aria-labelledby="healthcare-id-heading">
                <h2 id="healthcare-id-heading" class="text-base font-semibold">Printable healthcare ID</h2><p class="mt-1 text-xs text-muted">Your QR code identifies this patient by ID and contains no clinical information.</p>
                <div class="mt-5 overflow-x-auto rounded-lg border border-brand-blue bg-white p-3 sm:p-6">
                    <iframe x-ref="card" x-on:load="cardReady = true" src="{{ route('patients.card.show', $registered) }}" title="Patient {{ $registered->patient_id }} healthcare card preview" class="mx-auto block border-0 bg-white" style="width: 85.60mm; height: 53.98mm;"></iframe>
                </div>
                <p class="mt-3 text-[11px] leading-relaxed text-muted">Card size: 85.60 &times; 53.98 mm. Print at actual size (100%) with browser headers and footers disabled.</p>
                <div class="mt-4 flex flex-wrap gap-2"><button type="button" x-bind:disabled="!cardReady" x-on:click="$refs.card.contentWindow.focus(); $refs.card.contentWindow.print()" class="patient-button bg-brand-blue text-xs disabled:opacity-50">Print healthcare ID</button><a href="{{ route('patients.card.download', $registered) }}" class="patient-button text-xs">Download PDF</a><a href="{{ route('patients.card.show', $registered) }}" target="_blank" rel="noopener noreferrer" class="patient-button text-xs">Open printable ID</a></div>
            </section>
            <footer class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('medical-records.show', ['record' => $registered->patient_id]) }}" wire:navigate class="patient-button border-transparent bg-brand-pink text-xs">Open patient record</a>
                <button type="button" wire:click="resetForm" wire:loading.attr="disabled" class="patient-button bg-white text-xs">Register another patient</button>
                <a href="{{ route('queues.index') }}" wire:navigate class="patient-button bg-white text-xs">View queue</a>
            </footer>
            <p class="mt-3 text-xs text-muted">No queue number has been assigned. Check the patient in through Queue Management when they need a visit.</p>
        </div>
    @else
        <div class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
            <div><h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">New patient registration</h1><p class="mt-2 text-sm text-muted">Create one patient record for each person. Fields marked * are required.</p></div>
            <a href="{{ route('patients.index') }}" wire:navigate class="patient-button bg-white text-xs"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg>Back to patients</a>
        </div>
        <ol class="relative mb-6 grid gap-3 text-xs text-muted sm:grid-cols-3" aria-label="Registration progress">
            <li x-bind:class="! $wire.showReview ? 'font-semibold text-ink' : ''" x-bind:aria-current="! $wire.showReview ? 'step' : null">1 &nbsp; Patient information</li>
            <li x-bind:class="$wire.showReview ? 'font-semibold text-ink' : ''" x-bind:aria-current="$wire.showReview ? 'step' : null">2 &nbsp; Review details</li>
            <li>3 &nbsp; Healthcare ID</li>
        </ol>

        @if ($errors->any())
            <div role="alert" class="relative mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form wire:submit.prevent="savePatient" wire:confirm="Save this patient information?" class="space-y-5">
            <x-patient-information-fields :reviewing="$showReview" :details="$this->patientSummary" />

            @can('queue-register')
                @php($duplicates = $this->duplicatePatients)
                <aside class="relative flex gap-3 rounded-lg border border-line bg-blush/80 px-4 py-4 text-xs" aria-label="Duplicate patient check">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
                    <div>
                        @if (! filled($first_name) || ! filled($last_name) || ! filled($birthdate) || $age === '')
                            <p class="font-semibold">Duplicate check</p><p class="mt-1 text-[11px] leading-relaxed text-muted">Enter first name, last name, and birthdate to check existing records.</p>
                        @elseif ($duplicates->isEmpty())
                            <p class="font-semibold">Duplicate check: no matching record found</p><p class="mt-1 text-[11px] leading-relaxed text-muted">No existing records share this first name, last name, and birthdate.</p>
                        @else
                            <p class="font-semibold">Possible existing patient records</p><p class="mt-1 text-[11px] leading-relaxed text-muted">The first name, last name, and birthdate match. Review these records before registering another patient.</p>
                            <ul class="mt-2 space-y-2">@foreach ($duplicates as $duplicate)<li wire:key="registration-duplicate-{{ $duplicate->patient_id }}"><a href="{{ route('patients.edit', $duplicate) }}" target="_blank" rel="noopener noreferrer" class="font-medium underline decoration-brand-pink underline-offset-4">Patient #{{ $duplicate->patient_id }} &middot; {{ $duplicate->first_name }} {{ $duplicate->middle_name }} {{ $duplicate->last_name }} &middot; {{ $duplicate->status }}</a></li>@endforeach</ul>
                        @endif
                    </div>
                </aside>
            @endcan

            <footer class="relative flex flex-wrap items-center justify-between gap-4">
                <p class="text-[11px] text-muted">Review the entered details with the patient before confirming.</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('patients.index') }}" wire:navigate class="patient-button bg-white text-xs">Cancel</a>
                    @if ($showReview)
                        <button type="button" wire:click="returnToDetails" wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs hover:bg-[#90cfe9]">Back to details</button>
                    @else
                        <button type="button" wire:click="reviewDetails" wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs hover:bg-[#90cfe9]">Review details</button>
                    @endif
                    <button type="submit" wire:loading.attr="disabled" wire:target="savePatient,reviewDetails" aria-label="Save Patient Information" class="patient-button border-transparent bg-brand-pink text-xs hover:bg-[#f598ba] disabled:cursor-not-allowed"><span wire:loading.remove wire:target="savePatient">Register patient</span><span wire:loading wire:target="savePatient">Registering...</span></button>
                </div>
            </footer>
        </form>
    @endif
</div>
