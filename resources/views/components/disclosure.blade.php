@props(['name', 'open' => false, 'reveal' => false])

<details wire:key="disclosure-{{ $name }}" wire:ignore.self {{ $attributes }} @if ($open || $reveal) open @endif>
    @if ($reveal)
        <span wire:key="disclosure-reveal-{{ $name }}" x-data x-init="$el.closest('details').open = true" hidden></span>
    @endif
    {{ $slot }}
</details>
