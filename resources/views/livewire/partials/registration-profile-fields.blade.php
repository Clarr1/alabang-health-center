@php
    $schema = \App\Services\PatientRegistrationSchema::class;
    $householdFields = $schema::householdFields();
@endphp
<section @if (isset($registrationStep)) x-cloak x-show="$wire.registrationStep === 2 || $wire.showReview" @endif class="patient-card relative">
    <h2 class="text-base font-semibold">Household Information and Living Conditions</h2>
    <p class="mt-1 text-xs text-muted">One Family Number connects individual patients. Shared changes apply to all household members.</p>
    @if (! $reviewing && Gate::allows('queue-register') && ($registrationHouseholdId ?? null) === null)
        <label for="household-mode" class="patient-label mt-4">Household membership</label>
        <select id="household-mode" wire:model.live="householdMode" class="patient-input w-full"><option value="none">No household assigned</option><option value="new">Create a household</option><option value="existing">Select an existing household</option></select>
        @if ($householdMode === 'existing')
            <label for="household-search" class="patient-label mt-4">Search Family Number or household label</label>
            <input id="household-search" x-on:keydown.enter.prevent wire:model.live.debounce.300ms="householdSearch" type="search" maxlength="255" class="patient-input w-full">
            <ul class="mt-2 max-h-48 overflow-y-auto divide-y divide-line rounded border border-line">
                @forelse ($this->matchingHouseholds as $household)
                    <li wire:key="household-option-{{ $household->id }}"><button type="button" wire:click="selectHousehold({{ $household->id }})" class="w-full px-3 py-3 text-left text-xs {{ $householdId === $household->id ? 'bg-blush font-semibold' : '' }}">{{ $household->family_number }} · {{ $household->label }}<span class="mt-1 block text-muted">{{ $household->address }}</span></button></li>
                @empty<li class="p-3 text-xs text-muted">No households found. Create a new household if needed.</li>@endforelse
            </ul>
            @if ($householdId)
                <p class="mt-3 text-xs font-semibold">Selected Family Number: {{ \App\Models\Household::find($householdId)?->family_number }}</p>
                <label class="patient-label mt-3"><input type="checkbox" wire:model.live="updateHousehold" class="mr-2 rounded">Update this household's shared information</label>
            @endif
        @endif
        <x-input-error :messages="$errors->get('householdId')" class="mt-2" />
    @else
        <p class="mt-4 text-sm">{{ $householdMode === 'none' ? 'No household assigned.' : ($householdMode === 'new' ? 'New Family Number will be generated on save.' : 'Family Number: '.\App\Models\Household::find($householdId)?->family_number) }}</p>
    @endif
    @if ($householdMode !== 'none')
        <div class="mt-4">
            @if ($reviewing || Gate::denies('queue-register'))<p class="text-sm">Relationship: {{ $householdRelationship ?: 'Not provided.' }}</p>
            @else<label for="household-relationship" class="patient-label">Relationship to household (Father, Mother, Child, etc.)</label><input id="household-relationship" wire:model.blur="householdRelationship" maxlength="255" class="patient-input w-full">@endif
        </div>
        <x-disclosure name="registration-household" :open="$householdMode === 'new' || $updateHousehold || $reviewing" :reveal="$updateHousehold || $reviewing || $errors->has('householdData.*')" class="mt-4">
            <summary class="cursor-pointer text-sm font-semibold">Shared living conditions</summary>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($householdFields as $key => $field)
                    @include('livewire.partials.registration-field', ['prefix' => 'householdData', 'values' => $householdData, 'fields' => $householdFields, 'reviewing' => $reviewing || ($householdMode === 'existing' && ! $updateHousehold) || Gate::denies('queue-register')])
                @endforeach
            </dl>
        </x-disclosure>
    @endif
    @if (! $reviewing && $originalHouseholdId && ($householdMode !== 'existing' || $householdId !== $originalHouseholdId))
        <label for="transfer-reason" class="patient-label mt-4">Reason for household transfer / removal *</label><textarea id="transfer-reason" wire:model.blur="transferReason" class="patient-input w-full" maxlength="1000"></textarea><x-input-error :messages="$errors->get('transferReason')" />
    @endif
    @if ($reviewing && filled($transferReason))<p class="mt-4 text-sm">Transfer reason: {{ $transferReason }}</p>@endif
</section>
@foreach ($this->profileSections as $section => $fields)
    @php
        $sectionStep = match ($section) {
            'Personal Information' => 1,
            'Family and Membership Information' => 2,
            'Medical History', 'Immunization Child Information' => 4,
            'Form Completion' => 5,
            default => 3,
        };
    @endphp
    <x-disclosure name="profile-section-{{ md5($section) }}" :open="$reviewing || isset($registrationStep)" :reveal="$reviewing || collect(array_keys($fields))->contains(fn ($field) => $errors->has('profile.'.$field))" x-cloak x-show="{{ isset($registrationStep) ? '$wire.registrationStep === '.$sectionStep.' || $wire.showReview' : 'true' }}" class="patient-card relative">
        <summary class="cursor-pointer text-sm font-semibold">{{ $section }} @if ($schema::sensitive($section))<span class="ml-2 text-xs text-muted">Restricted clinical assessment</span>@endif</summary>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($fields as $key => $field)
                @include('livewire.partials.registration-field', ['prefix' => 'profile', 'values' => $profile, 'reviewing' => $reviewing])
            @endforeach
        </dl>
        @if ($section === 'Form Completion' && ($editing ?? true))<p class="mt-4 text-xs text-muted">Prepared by: {{ auth()->user()->name }}. Staff identity and completion time are recorded automatically when saved.</p>@endif
        @if ($section === 'Immunization Child Information')<p class="mt-4 text-xs text-muted">Baby name, birthdate, and age use the individual patient's Personal Information.</p>@endif
    </x-disclosure>
@endforeach
<x-disclosure name="registration-conditions" :open="$reviewing || isset($registrationStep)" :reveal="$reviewing || $errors->has('selectedConditions*') || $errors->has('otherCondition')" x-cloak x-show="{{ isset($registrationStep) ? '$wire.registrationStep === 4 || $wire.showReview' : 'true' }}" class="patient-card relative">
    <summary class="cursor-pointer text-sm font-semibold">Non-Communicable Diseases (NCD)</summary>
    <p class="mt-3 text-xs text-muted">Selections record reported history. A checkbox does not confirm a diagnosis.</p>
    @if ($reviewing)
        <p class="mt-3 text-sm">{{ implode(', ', $selectedConditions) ?: 'No conditions selected.' }}</p><p class="mt-2 text-sm">Other: {{ $otherCondition ?: 'Not provided.' }}</p>
    @else
        <div class="mt-4 flex flex-wrap gap-4">@foreach ($schema::CONDITIONS as $condition)<label wire:key="condition-{{ $condition }}" class="text-sm"><input type="checkbox" wire:model.live="selectedConditions" value="{{ $condition }}" class="mr-2 rounded">{{ $condition }}</label>@endforeach</div>
        <label for="other-condition" class="patient-label mt-4">Other condition (specify)</label><input id="other-condition" wire:model.blur="otherCondition" maxlength="255" class="patient-input w-full">
    @endif
</x-disclosure>
<x-disclosure name="registration-doses" :open="$reviewing || count($newDoses) > 0 || isset($registrationStep)" :reveal="$reviewing || count($newDoses) > 0 || $errors->has('newDoses*')" x-cloak x-show="{{ isset($registrationStep) ? '$wire.registrationStep === 4 || $wire.showReview' : 'true' }}" class="patient-card relative">
    <summary class="cursor-pointer text-sm font-semibold">Immunization Records — new doses</summary>
    <p class="mt-3 text-xs text-muted">Record documented doses. Paper-form age columns are labels for recording, and do not prescribe a schedule. Completed doses require an administration date.</p>
    @foreach ($newDoses as $index => $dose)
        <fieldset wire:key="new-dose-{{ $newDoseKeys[$index] ?? $index }}" class="mt-4 rounded border border-line p-4">
            <legend class="px-1 text-sm font-semibold">Dose entry {{ $index + 1 }}</legend>
            @if ($reviewing)
                <dl class="grid gap-3 sm:grid-cols-3">@foreach ($dose as $key => $value)@if ($key !== 'supersedes_id' && filled($value))<div><dt class="text-xs text-muted">{{ str_replace('_', ' ', $key) }}</dt><dd class="break-words text-sm">{{ $value }}</dd></div>@endif@endforeach</dl>
            @else
                @php
                    $doseFields = [
                        'vaccine_name' => ['label' => 'Vaccine / supplement', 'type' => 'select', 'options' => $schema::VACCINES],
                        'other_vaccine' => ['label' => 'Other vaccine / supplement', 'type' => 'text', 'when' => ['vaccine_name', 'Others']],
                        'schedule' => ['label' => 'Schedule label', 'type' => 'select', 'options' => $schema::SCHEDULES],
                        'other_schedule' => ['label' => 'Other schedule', 'type' => 'text', 'when' => ['schedule', 'Other']],
                        'dose' => ['label' => 'Dose / sequence', 'type' => 'text'],
                        'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['Pending', 'Completed', 'Missed']],
                        'administered_at' => ['label' => 'Date administered', 'type' => 'date', 'when' => ['status', 'Completed']],
                        'administered_by' => ['label' => 'Administered by (if known)', 'type' => 'text'],
                        'remarks' => ['label' => 'Remarks', 'type' => 'textarea'],
                    ];
                @endphp
                <div class="grid gap-4 sm:grid-cols-3">@foreach ($doseFields as $key => $field)@include('livewire.partials.registration-field', ['prefix' => 'newDoses.'.$index, 'values' => $dose, 'fields' => $doseFields, 'reviewing' => false])@endforeach</div>
                <button type="button" wire:click="removeDose({{ $index }})" class="patient-button mt-3 text-xs">Remove unsaved dose</button>
            @endif
        </fieldset>
    @endforeach
    @if (! $reviewing)<button type="button" wire:click="addDose" class="patient-button mt-4 text-xs">Add vaccine / supplement dose</button>@elseif (! count($newDoses))<p class="mt-3 text-sm">No new doses entered.</p>@endif
</x-disclosure>
