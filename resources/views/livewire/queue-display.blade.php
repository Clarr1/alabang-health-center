<main wire:poll.2s.keep-alive
    x-data="{ fullscreen: false, fullscreenError: '',
        async toggleFullscreen() {
            this.fullscreenError = '';
            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen();
                } else {
                    await document.documentElement.requestFullscreen();
                }
            } catch (error) {
                this.fullscreenError = 'Fullscreen is unavailable. Use your browser fullscreen option.';
            }
        }
    }"
    x-on:fullscreenchange.document="fullscreen = !!document.fullscreenElement"
    class="flex min-h-screen flex-col bg-white">
    <header class="flex flex-wrap items-center justify-between gap-4 bg-gray-800 px-5 py-4 text-white sm:px-8">
        <div>
            <h1 class="text-2xl font-bold sm:text-3xl">Alabang Health Center</h1>
            <p class="mt-1 text-sm text-gray-300 sm:text-base">{{ $displayDate }} · Asia/Manila</p>
        </div>
        <button type="button" x-on:click="toggleFullscreen()" x-bind:aria-pressed="fullscreen"
            class="rounded border border-gray-500 px-4 py-2 text-base font-semibold hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-white">
            <span x-text="fullscreen ? 'Exit Fullscreen' : 'Fullscreen'">Fullscreen</span>
        </button>
    </header>
    <p x-cloak x-show="fullscreenError" x-text="fullscreenError" role="alert" class="px-6 py-3 text-lg text-red-700"></p>

    <div class="grid flex-1 gap-8 p-5 sm:p-8 lg:grid-cols-2">
        <section aria-labelledby="now-serving-heading">
            <div class="mb-5 flex items-center justify-between border-b border-gray-200 pb-3">
                <h2 id="now-serving-heading" class="text-2xl font-bold text-gray-800 sm:text-3xl">Now Serving</h2>
                <span class="text-sm font-medium uppercase tracking-wider text-gray-500">Proceed to</span>
            </div>
            <div class="space-y-7">
                @foreach ($stages as $key => $stage)
                    <section wire:key="display-stage-{{ $key }}" aria-labelledby="stage-{{ $key }}">
                        <h3 id="stage-{{ $key }}" class="mb-2 text-lg font-semibold text-gray-600">{{ $stage['name'] }}</h3>
                        <div aria-live="polite" aria-atomic="true" class="space-y-2">
                            @forelse ($stage['serving'] as $entry)
                                <div wire:key="serving-{{ $displayDay }}-{{ $key }}-{{ $entry['number'] }}"
                                    class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 rounded border bg-white px-4 py-3 {{ $entry['highlighted'] ? 'border-red-500 border-l-8 text-red-600' : 'border-gray-200 text-emerald-700' }}">
                                    <div class="min-w-0">
                                        <p class="break-all text-4xl font-bold tabular-nums sm:text-5xl xl:text-6xl">{{ $entry['number'] }}</p>
                                        @if ($entry['highlighted'])
                                            <p class="mt-1 text-xs font-bold uppercase tracking-wider sm:text-sm">Proceed now</p>
                                        @endif
                                    </div>
                                    <svg aria-hidden="true" class="h-8 w-8 {{ $entry['highlighted'] ? 'text-red-500' : 'text-gray-300' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16m-6-6 6 6-6 6" />
                                    </svg>
                                    <p class="text-right text-lg font-semibold leading-tight sm:text-2xl">{{ $stage['name'] }}</p>
                                </div>
                            @empty
                                <p class="rounded border border-gray-200 bg-white px-4 py-5 text-lg text-gray-500">Nobody is being served here right now.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        <aside class="flex flex-col gap-6">
            <section aria-labelledby="up-next-heading">
                <h2 id="up-next-heading" class="mb-5 border-b border-gray-200 pb-3 text-2xl font-bold text-gray-800 sm:text-3xl">Up Next</h2>
                <div class="space-y-5">
                    @foreach ($stages as $key => $stage)
                        <div wire:key="up-next-stage-{{ $key }}">
                            <h3 class="mb-2 text-base font-semibold text-gray-600">{{ $stage['name'] }}</h3>
                            <div class="flex flex-wrap gap-x-6 gap-y-2">
                                @forelse ($stage['waiting'] as $number)
                                    <span wire:key="waiting-{{ $displayDay }}-{{ $key }}-{{ $number }}"
                                        class="text-3xl font-bold tabular-nums text-amber-700 sm:text-4xl">{{ $number }}</span>
                                @empty
                                    <p class="text-lg text-gray-500">No waiting numbers.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>

    <footer class="flex flex-wrap items-center justify-between gap-2 bg-gray-800 px-5 py-3 text-sm text-white sm:px-8 sm:text-base">
        <p>Please proceed to the service area shown beside your number.</p>
        <p class="font-semibold">Alabang Health Center</p>
    </footer>
</main>
