@props(['active' => false, 'label'])

<a {{ $attributes->class([
        'flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
        'bg-blue-50 text-blue-700' => $active,
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => ! $active,
    ]) }}
   wire:navigate x-on:click="mobileOpen = false" aria-label="{{ $label }}" title="{{ $label }}"
   @if ($active) aria-current="page" @endif>
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        {{ $slot }}
    </svg>
    <span x-show="!collapsed || !isDesktop" class="whitespace-nowrap">{{ $label }}</span>
</a>
