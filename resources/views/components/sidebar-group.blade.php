@props(['id', 'label', 'active' => false])

<div x-data="sidebarGroup" data-sidebar-group="{{ $id }}" data-active="{{ $active ? 'true' : 'false' }}"
     x-on:livewire:navigated.document="syncActiveGroup()"
     x-on:keydown.arrow-left.prevent.stop="closeGroup()">
    <button id="{{ $id }}-toggle" x-ref="toggle" type="button"
            x-on:click="toggleGroup()" x-on:keydown.arrow-right.prevent="expandGroup(true)"
            x-on:keydown.arrow-down.prevent="expandGroup(true)"
            aria-label="{{ $label }}" title="{{ $label }}" aria-controls="{{ $id }}-children"
            aria-expanded="{{ $active ? 'true' : 'false' }}" x-bind:aria-expanded="open && (!collapsed || !isDesktop)"
            @class(['flex w-full items-center gap-3 rounded-lg px-3 py-3 text-xs font-medium text-ink transition-colors focus:outline-none focus:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-400',
                'bg-gray-100 font-semibold' => $active, 'hover:bg-gray-50' => ! $active])>
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{{ $icon }}</svg>
        <span x-show="!collapsed || !isDesktop" class="min-w-0 flex-1 text-left leading-5">{{ $label }}</span>
        <svg x-show="!collapsed || !isDesktop" x-bind:class="{ 'rotate-180': open }" class="h-3.5 w-3.5 shrink-0 transition-transform motion-reduce:transition-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
    </button>
    <div id="{{ $id }}-children" x-ref="children" x-cloak x-show="open && (!collapsed || !isDesktop)"
         role="group" aria-labelledby="{{ $id }}-toggle" class="ml-5 mt-1 space-y-1 border-l border-line pl-2">
        {{ $slot }}
    </div>
</div>
