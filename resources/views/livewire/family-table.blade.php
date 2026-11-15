<div class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8">
    <header class="mb-6">
        <nav aria-label="Breadcrumb" class="mb-2 text-xs text-muted"><a href="{{ route('patients.index') }}" wire:navigate>Patient Record</a> / <span aria-current="page">Family Records</span></nav>
        <h1 class="text-2xl font-semibold tracking-tight">Family Records</h1>
        <p class="mt-2 text-sm text-muted">Find a household and open each member's individual medical history.</p>
    </header>
    <x-patient-record-nav active="family" />
    <section class="patient-card mb-5" aria-label="Family search and filters">
        <form wire:submit="$refresh" class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_220px_auto]">
            <div><label for="family-search" class="patient-label">Family Number, family name, or household address</label><input id="family-search" type="search" wire:model.live.debounce.300ms="search" class="patient-input w-full" placeholder="Search households…"></div>
            <div><label for="family-members-filter" class="patient-label">Household membership</label><select id="family-members-filter" wire:model.live="memberFilter" class="patient-input w-full"><option value="">All households</option><option value="with-members">With registered members</option><option value="without-members">Without registered members</option></select></div>
            <button type="submit" class="patient-button bg-brand-blue" wire:loading.attr="disabled">Search</button>
        </form>
    </section>
    <section class="patient-card" aria-labelledby="family-directory-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 id="family-directory-title" class="text-base font-semibold">Household directory</h2><span wire:loading.delay role="status" class="text-xs text-muted">Loading households…</span></div>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Family records table">
            <table class="w-full min-w-[850px] text-left text-xs">
                <thead class="bg-blush"><tr>@foreach (['Family Number', 'Family name', 'Household address', 'Members', 'Registered', 'Actions'] as $heading)<th scope="col" class="px-3 py-3">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($this->households as $household)
                        <tr wire:key="family-{{ $household->id }}"><th scope="row" class="px-3 py-4">{{ $household->family_number }}</th><td class="px-3 py-4">{{ $household->label }}</td><td class="max-w-xs whitespace-pre-line break-words px-3 py-4">{{ $household->address }}</td><td class="px-3 py-4">{{ $household->members_count }}</td><td class="whitespace-nowrap px-3 py-4">{{ $household->created_at?->timezone('Asia/Manila')->format('d M Y') ?: 'Not recorded' }}</td><td class="px-3 py-4"><a href="{{ route('families.show', ['household' => $household->id, 'search' => $search, 'memberFilter' => $memberFilter, 'page' => $this->households->currentPage()]) }}" wire:navigate class="patient-button whitespace-nowrap text-xs">View Family Record</a></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-10 text-center"><p class="text-sm font-semibold">No households found.</p><p class="mt-2 text-xs text-muted">Try another Family Number, family name, or address.</p>@if ($search !== '' || $memberFilter !== '')<button type="button" wire:click="resetFilters" class="patient-button mt-4 text-xs">Clear search and filters</button>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $this->households->links() }}</div>
    </section>
</div>
