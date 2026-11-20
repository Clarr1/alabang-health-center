<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name', 'Laravel') }}</title>
        @livewireStyles
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="sidebarNavigation" x-bind:style="{ '--sidebar-width': collapsed ? '5rem' : '14rem' }"
             style="--sidebar-width: 14rem"
             x-on:keydown.escape.window="mobileOpen = false"
             x-on:resize.window.debounce.150ms="isDesktop = window.innerWidth >= 1024; if (isDesktop) mobileOpen = false"
             x-on:livewire:navigating.document="mobileOpen = false"
             class="min-h-screen bg-blush text-ink">
            @include('layouts.navigation')

            <div class="min-w-0 transition-[margin] duration-200 motion-reduce:transition-none lg:ml-[var(--sidebar-width)]">
                @if (config('surveillance.demo'))
                    <div role="status" class="border-b border-amber-300 bg-amber-100 px-4 py-3 text-sm font-semibold text-amber-900">
                        FICTIONAL DEMO DATA - Separate test database. Patients, clinical rules, and alerts are simulated for software testing. No clinical approval is implied.
                    </div>
                @endif
                <header class="flex min-h-16 flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-line bg-white px-4 py-3 text-xs sm:px-7 lg:px-8">
                    <div class="flex items-center gap-2">
                        <button x-ref="sidebarToggle" type="button" x-on:click="$el.focus(); mobileOpen = true"
                                x-bind:aria-expanded="mobileOpen" aria-controls="app-sidebar" aria-label="Open navigation menu"
                                class="rounded-md p-2 text-ink hover:bg-blush focus:outline-none focus:ring-2 focus:ring-brand-blue lg:hidden">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        <span class="hidden text-muted sm:inline">Patient management &amp; medical records</span>
                        <span class="font-medium sm:hidden">Alabang Health Center</span>
                    </div>
                    <div class="flex items-center gap-4 max-sm:w-full max-sm:justify-end">
                        @can('surveillance-view')
                            @if (\Illuminate\Support\Facades\Schema::hasColumn('surveillance_alerts', 'surveillance_result_id'))
                                <a href="{{ route('surveillance.alerts') }}" wire:navigate class="rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-800" aria-label="New surveillance alerts">
                                    Surveillance: {{ \App\Models\SurveillanceAlert::whereNotNull('surveillance_result_id')->where('status', 'New')->count() }} new
                                </a>
                            @endif
                        @endcan
                        <time datetime="{{ now('Asia/Manila')->toDateString() }}">{{ now('Asia/Manila')->format('D, d M Y') }}</time>
                        <span class="flex items-center gap-2 text-muted">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
                            Secure session
                        </span>
                    </div>
                </header>
                @isset($header)
                    <header class="border-b border-gray-200 bg-white">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main id="main-content" class="min-w-0">
                    {{ $slot }}
                </main>
            </div>
        </div>
        @livewireScriptConfig
    </body>
</html>
