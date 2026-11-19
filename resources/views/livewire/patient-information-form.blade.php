<div wire:poll.60s x-data class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    <nav aria-label="Breadcrumb" class="relative mb-3 text-xs text-muted"><ol class="flex flex-wrap items-center gap-2"><li>Patient Management</li><li aria-hidden="true">/</li><li aria-current="page">Patient Registration</li></ol></nav>
    @if ($registrationHouseholdId !== null)
        @php($registrationHousehold = \App\Models\Household::findOrFail($registrationHouseholdId))
        <div class="relative mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line bg-blush/80 p-4 text-sm">
            <p>Adding a family member to <strong>{{ $registrationHousehold->label }} &middot; {{ $registrationHousehold->family_number }}</strong>. The existing household is preselected.</p>
            <a href="{{ route('families.show', $registrationHousehold) }}" wire:navigate class="patient-button bg-white text-xs">Back to Family Record</a>
        </div>
    @endif
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
                <a href="{{ auth()->user()->can('medical-records-view') ? route('medical-records.show', ['record' => $registered->patient_id]) : route('patients.edit', $registered) }}" wire:navigate class="patient-button border-transparent bg-brand-pink text-xs">Open patient record</a>
                @if ($registered->household_id !== null)<a href="{{ route('families.show', $registered->household_id) }}" wire:navigate class="patient-button bg-white text-xs">Open Family Record</a>@endif
                <button type="button" wire:click="resetForm" wire:loading.attr="disabled" class="patient-button bg-white text-xs">Register another patient</button>
                <a href="{{ route('queues.index') }}" wire:navigate class="patient-button bg-white text-xs">View queue</a>
            </footer>
            <p class="mt-3 text-xs text-muted">No queue number has been assigned. Check the patient in through Queue Management when they need a visit.</p>
        </div>
    @else
        <div class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
            <div><h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Patient Registration</h1><p class="mt-2 text-sm text-muted">Patient Registration · Fields marked * are required. Optional assessments can be skipped.</p></div>
            <a href="{{ route('patients.index') }}" wire:navigate class="patient-button bg-white text-xs"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg>Back to Patient Record</a>
        </div>
        <ol class="relative mb-6 grid gap-3 text-xs text-muted sm:grid-cols-5" aria-label="Registration progress">
            @foreach ([1 => 'Personal information', 2 => 'Family and household', 3 => 'Health profile', 4 => 'History and immunization', 5 => 'Review and save'] as $step => $label)
                <li aria-current="{{ $registrationStep === $step ? 'step' : 'false' }}" @class(['font-semibold text-ink' => $registrationStep === $step])>@if ($step < $registrationStep)<button type="button" wire:click="goToRegistrationStep({{ $step }})" class="text-left underline underline-offset-4">{{ $step }}. {{ $label }}</button>@else{{ $step }}. {{ $label }}@endif</li>
            @endforeach
        </ol>

        @if ($errors->any())
            <div role="alert" class="relative mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form wire:submit.prevent="submitRegistration" novalidate class="space-y-5">
            <div x-show="$wire.registrationStep === 1 || $wire.showReview" class="space-y-5"><x-patient-information-fields :reviewing="$showReview" :details="$this->patientSummary" /></div>
            @include('livewire.partials.patient-category', ['reviewing' => $showReview, 'automaticCategory' => true])
            @if ($patientCategory !== null)
                @include('livewire.partials.registration-profile-fields', ['reviewing' => $showReview])
            @endif

            @can('queue-register')
                @php($duplicates = $this->duplicatePatients)
                <aside x-show="$wire.registrationStep === 1 || $wire.showReview" class="relative flex gap-3 rounded-lg border border-line bg-blush/80 px-4 py-4 text-xs" aria-label="Duplicate patient check">
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
                    @if ($showReview)
                        <button type="button" wire:click="returnToDetails" wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs hover:bg-[#90cfe9]">Back to details</button>
                    @else
                        @if ($registrationStep > 1)<button type="button" wire:click="goToRegistrationStep({{ $registrationStep - 1 }})" class="patient-button bg-white text-xs">Back</button>@endif
                        <button type="button" wire:click="continueRegistration" wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs hover:bg-[#90cfe9]">{{ $registrationStep === 4 ? 'Continue to review' : 'Continue' }}</button>
                    @endif
                    @if ($showReview)<button type="submit" wire:loading.attr="disabled" aria-label="Save Patient Information" class="patient-button border-transparent bg-brand-pink text-xs hover:bg-[#f598ba] disabled:cursor-not-allowed">Register patient</button>@endif
                </div>
            </footer>
        </form>
    @endif
</div>
