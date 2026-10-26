<div x-cloak x-show="mobileOpen && !isDesktop" x-transition.opacity
     x-on:click="mobileOpen = false" class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" aria-hidden="true" data-sidebar-overlay></div>

<aside id="app-sidebar" x-cloak
       x-bind:class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
       x-bind:inert="!isDesktop && !mobileOpen"
       x-bind:aria-hidden="!isDesktop && !mobileOpen ? 'true' : null"
       x-trap.inert.noscroll="mobileOpen && !isDesktop"
       class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-gray-200 bg-white transition-all duration-200 motion-reduce:transition-none lg:w-[var(--sidebar-width)] lg:translate-x-0"
       aria-label="Sidebar navigation">
    <div class="shrink-0 border-b border-gray-100 px-4 pb-4 pt-6">
        <div class="flex items-center justify-between gap-2">
            <a href="{{ route('dashboard') }}" wire:navigate x-on:click="mobileOpen = false"
               x-show="!collapsed || !isDesktop" class="min-w-0 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
               aria-label="Alabang Health Center dashboard" title="Alabang Health Center">
                <span x-show="!collapsed || !isDesktop" class="text-sm font-bold leading-5 text-gray-900">Alabang<br>Health Center</span>
            </a>
            <button type="button" x-on:click="collapsed = !collapsed"
                    x-bind:aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    x-bind:title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    x-bind:aria-expanded="!collapsed" aria-controls="app-sidebar" data-sidebar-collapse
                    class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 lg:flex">
                <svg x-bind:class="{ 'rotate-180': collapsed }" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 7-5 5 5 5" /></svg>
            </button>
            <button x-ref="sidebarClose" type="button" x-on:click="mobileOpen = false"
                    class="rounded-md p-2 text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 lg:hidden"
                    aria-label="Close navigation menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg>
            </button>
        </div>
    </div>

    <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Main navigation">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" label="Dashboard">
            <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />
        </x-sidebar-link>
        @can('queue-view')
            <x-sidebar-link :href="route('queues.index')" :active="request()->routeIs('queues.*') && ! request()->routeIs('queues.consultation')" label="Queue Management">
                <path d="M9 6h12M9 12h12M9 18h12M3 6h.01M3 12h.01M3 18h.01" />
            </x-sidebar-link>
        @endcan
        <x-sidebar-link :href="route('patients.index')" :active="request()->routeIs('patients.*', 'patient.information-form', 'patient.vital-signs-form')" label="Patients">
            <circle cx="9" cy="8" r="3" /><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 4v2" />
        </x-sidebar-link>
        @can('queue-view')
            <x-sidebar-link :href="route('medical-records.index')" :active="request()->routeIs('medical-records.*')" label="Medical Records">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5" />
            </x-sidebar-link>
        @endcan
        @can('queue-consult')
            <x-sidebar-link :href="route('doctors-consultation.index')" :active="request()->routeIs('doctors-consultation.*', 'queues.consultation')" label="Doctor's Consultation">
                <rect x="4" y="4" width="16" height="18" rx="2" /><path d="M9 2h6v4H9zM12 10v6M9 13h6" />
            </x-sidebar-link>
        @endcan
    </nav>

    <div class="shrink-0 space-y-2 border-t border-gray-100 p-3">
        <a href="{{ route('profile.edit') }}" wire:navigate x-on:click="mobileOpen = false"
           aria-label="User profile" title="{{ Auth::user()->name }} — Profile"
           @if (request()->routeIs('profile.*')) aria-current="page" @endif
           @class(['flex items-center gap-3 rounded-lg px-2 py-3 text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
               'bg-blue-50 text-blue-700' => request()->routeIs('profile.*'),
               'text-gray-600 hover:bg-gray-100' => ! request()->routeIs('profile.*')])>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 font-semibold text-blue-700">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
            <span x-show="!collapsed || !isDesktop" class="min-w-0">
                <span class="block truncate font-semibold text-gray-900">{{ Auth::user()->name }}</span>
                <span class="block truncate text-xs text-gray-500">{{ Auth::user()->email }}</span>
                <span class="mt-1 block text-xs text-gray-500">Profile</span>
            </span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" aria-label="Log Out" title="Log Out"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
                <span x-show="!collapsed || !isDesktop">Log Out</span>
            </button>
        </form>
    </div>
</aside>
