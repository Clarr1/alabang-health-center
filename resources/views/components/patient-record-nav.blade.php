@props(['active' => 'individual'])

<nav aria-label="Patient record views" class="mb-6 flex flex-wrap gap-2 border-b border-line pb-4 text-sm">
    <a href="{{ route('patients.index') }}" wire:navigate @if ($active === 'individual') aria-current="page" @endif @class(['patient-button', 'bg-brand-blue' => $active === 'individual', 'bg-white' => $active !== 'individual'])>Individual Patient Records</a>
    @can('queue-view')
        <a href="{{ route('families.index') }}" wire:navigate @if ($active === 'family') aria-current="page" @endif @class(['patient-button', 'bg-brand-blue' => $active === 'family', 'bg-white' => $active !== 'family'])>Family Records</a>
    @endcan
</nav>
