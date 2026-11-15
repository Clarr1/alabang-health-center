<div class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8">
    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <nav aria-label="Breadcrumb" class="mb-2 text-xs text-muted"><a href="{{ route('patients.index') }}" wire:navigate>Patient Record</a> / <a href="{{ $backUrl }}" wire:navigate>Family Records</a> / <span aria-current="page">{{ $household->label }}</span>@if ($selectedMember) / {{ $selectedMember->first_name }} {{ $selectedMember->last_name }}@endif</nav>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $household->label }}</h1>
            <p class="mt-2 text-sm text-muted">{{ $household->family_number }} · Family Record</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ $backUrl }}" wire:navigate class="patient-button text-xs">Back to Family Records</a>@can('queue-register')<a href="{{ route('patient.information-form', ['household' => $household->id]) }}" wire:navigate class="patient-button bg-brand-pink text-xs">Add Family Member</a>@endcan</div>
    </header>
    <x-patient-record-nav active="family" />
    <section class="patient-card" aria-labelledby="family-overview-title">
        <h2 id="family-overview-title" class="text-base font-semibold">Family information overview</h2>
        <dl class="mt-4 grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['Family Number' => $household->family_number, 'Family name' => $household->label, 'Household address' => $household->address, 'Registered members' => $members->count(), 'Active members' => $members->where('status', 'Active')->count(), 'Registered date' => $household->created_at?->timezone('Asia/Manila')->format('d M Y')] as $label => $value)
                <div><dt class="text-xs text-muted">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $value ?? 'Not recorded' }}</dd></div>
            @endforeach
        </dl>
        <details class="mt-5 border-t border-line pt-4"><summary class="cursor-pointer text-sm font-semibold">Shared living conditions</summary><dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (\App\Services\PatientRegistrationSchema::householdFields() as $key => $field)
                @continue(in_array($key, ['label', 'address'], true))
                @if (\App\Services\PatientRegistrationSchema::visible($key, \App\Services\PatientRegistrationSchema::householdFields(), $household->getAttributes()))<div><dt class="text-xs text-muted">{{ $field['label'] }}</dt><dd class="mt-1 whitespace-pre-line break-words text-sm">{{ filled($household->{$key}) ? ($key === 'average_income' ? '₱'.number_format((float) $household->{$key}, 2) : $household->{$key}) : 'Not provided.' }}</dd></div>@endif
            @endforeach
        </dl></details>
    </section>
    <section class="patient-card mt-5" aria-labelledby="family-members-title">
        <h2 id="family-members-title" class="text-base font-semibold">Registered family members</h2>
        <p class="mt-2 text-xs text-muted">Select one member to view their own profile, consultations, vital signs, and follow-ups. Household membership does not combine medical records.</p>
        <div class="mt-5 overflow-x-auto rounded-t-md" tabindex="0" role="region" aria-label="Registered family members table, scroll horizontally for all columns">
            <table class="w-full min-w-[850px] text-left text-xs">
                <thead class="bg-blush text-[11px]">
                    <tr>
                        <th scope="col" class="px-3 py-3 font-semibold">Patient / Unique ID</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Relationship</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Age / Birthday</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Patient category</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line border-b border-line">
                    @forelse ($members as $member)
                        <tr wire:key="family-member-{{ $member->patient_id }}" @class(['align-top transition-colors hover:bg-blush/60', 'bg-brand-blue/20' => $selectedMemberId === $member->patient_id])>
                            <th scope="row" class="px-3 py-4 font-normal">
                                <span class="block font-semibold">{{ trim(implode(' ', array_filter([$member->first_name, $member->middle_name, $member->last_name]))) }}</span>
                                <span class="mt-1 block text-[11px] text-muted">Patient #{{ $member->patient_id }}</span>
                            </th>
                            <td class="px-3 py-4">{{ $member->household_relationship ?: 'Relationship not recorded' }}</td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <span class="block">{{ app(\App\Services\PatientCategories::class)->age($member->birthdate) ?? 'Not recorded' }}</span>
                                <span class="mt-1 block text-[11px] text-muted">{{ $member->birthdate }}</span>
                            </td>
                            <td class="px-3 py-4">{{ $member->patient_category ?? app(\App\Services\PatientCategories::class)->suggest($member->birthdate) ?? 'Not recorded' }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2 whitespace-nowrap">
                                    <button type="button" wire:click="selectMember({{ $member->patient_id }})" wire:loading.attr="disabled" aria-label="View medical summary" aria-pressed="{{ $selectedMemberId === $member->patient_id ? 'true' : 'false' }}" class="patient-button bg-brand-blue px-3 py-2 text-xs">Summary</button>
                                    <a href="{{ route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $member->patient_id]) }}" wire:navigate aria-label="View Patient Record" class="patient-button px-3 py-2 text-xs">Open record</a>
                                    @can('queue-register')<a href="{{ route('patients.edit', $member) }}" wire:navigate aria-label="Edit patient information" class="rounded px-1 py-2 text-xs font-medium hover:text-pink-700 focus-visible:outline-pink-500">Edit</a>@endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-10 text-center text-sm text-muted">No registered members in this household.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    @if ($selectedMember)
        <section class="mt-6" aria-label="Selected family member medical history">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">Individual medical history · {{ $selectedMember->first_name }} {{ $selectedMember->last_name }}</h2><div class="flex flex-wrap gap-2"><a href="{{ route('families.members.show', ['familyHouseholdId' => $household->id, 'record' => $selectedMember->patient_id]) }}" wire:navigate class="patient-button text-xs">View Full Medical History</a><button type="button" wire:click="closeMember" class="patient-button text-xs">Close member history</button></div></div>
            <livewire:medical-record-details :record="$selectedMember" :family-household-id="$household->id" :embedded="true" :key="'family-history-'.$household->id.'-'.$selectedMember->patient_id" />
        </section>
    @endif
</div>
