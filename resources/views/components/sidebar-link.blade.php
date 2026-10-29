@props(['active' => false, 'label', 'displayLabel' => null])

<a {{ $attributes->class([
        'flex items-center gap-3 rounded-lg px-3 py-3 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-brand-blue',
        'bg-brand-pink text-ink' => $active,
        'text-ink hover:bg-blush' => ! $active,
    ]) }}
   wire:navigate x-on:click="mobileOpen = false" aria-label="{{ $label }}" title="{{ $displayLabel ?? $label }}"
   @if ($active) aria-current="page" @endif>
    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        {{ $slot }}
    </svg>
    <span x-show="!collapsed || !isDesktop" class="whitespace-nowrap">{{ $displayLabel ?? $label }}</span>
</a>
