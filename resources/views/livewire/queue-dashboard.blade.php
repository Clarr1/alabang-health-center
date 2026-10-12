<section wire:poll.10s class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="Today's queue statistics">
    @foreach ($statistics as $label => $count)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-600">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold text-gray-900">{{ $count }}</p>
        </div>
    @endforeach
</section>
