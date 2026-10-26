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
        <div x-data="sidebarNavigation" x-bind:style="{ '--sidebar-width': collapsed ? '5rem' : '16rem' }"
             style="--sidebar-width: 16rem"
             x-on:keydown.escape.window="mobileOpen = false"
             x-on:resize.window.debounce.150ms="isDesktop = window.innerWidth >= 1024; if (isDesktop) mobileOpen = false"
             x-on:livewire:navigating.document="mobileOpen = false"
             class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <div class="min-w-0 transition-[margin] duration-200 motion-reduce:transition-none lg:ml-[var(--sidebar-width)]">
                <header class="flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 lg:hidden">
                    <button x-ref="sidebarToggle" type="button" x-on:click="mobileOpen = true; $nextTick(() => $refs.sidebarClose.focus())"
                            x-bind:aria-expanded="mobileOpen" aria-controls="app-sidebar" aria-label="Open navigation menu"
                            class="rounded-md p-2 text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <span class="text-sm font-semibold text-gray-900">Alabang Health Center</span>
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
