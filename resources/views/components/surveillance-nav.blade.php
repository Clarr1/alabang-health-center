@props(['active'])
<nav aria-label="Syndromic Surveillance" class="flex flex-wrap gap-2 rounded-xl border border-line bg-white p-2">
    @foreach (['surveillance.index' => 'Overview', 'surveillance.alerts' => 'Surveillance Alerts', 'surveillance.definitions' => 'Syndrome Categories', 'surveillance.settings' => 'Detection Settings', 'surveillance.history' => 'Surveillance History'] as $name => $label)
        @if (($name !== 'surveillance.definitions' || auth()->user()->can('surveillance-approve')) && ($name !== 'surveillance.settings' || auth()->user()->can('surveillance-manage')))
            <a href="{{ route($name) }}" wire:navigate @if ($active === $name) aria-current="page" @endif @class(['patient-button border-transparent', 'bg-brand-blue' => $active === $name])>{{ $label }}</a>
        @endif
    @endforeach
</nav>
