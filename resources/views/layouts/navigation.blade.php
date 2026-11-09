<div x-cloak x-show="mobileOpen && !isDesktop" x-transition.opacity
     x-on:click="mobileOpen = false" class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" aria-hidden="true" data-sidebar-overlay></div>

<aside id="app-sidebar" x-cloak
       x-bind:class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
       x-bind:inert="!isDesktop && !mobileOpen"
       x-bind:aria-hidden="!isDesktop && !mobileOpen ? 'true' : null"
       x-trap.inert.noscroll="mobileOpen && !isDesktop"
       class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-line bg-white transition-all duration-200 motion-reduce:transition-none lg:w-[var(--sidebar-width)] lg:translate-x-0"
       aria-label="Sidebar navigation">
    <div class="shrink-0 px-4 pb-5 pt-7">
        <div class="flex items-center justify-between gap-2">
            <a href="{{ route('dashboard') }}" wire:navigate x-on:click="mobileOpen = false"
               class="flex min-w-0 items-center gap-3 rounded-md focus:outline-none focus:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-400"
               aria-label="Alabang Health Center dashboard" title="Alabang Health Center">
                <img src="{{ asset('images/barangay-alabang.png') }}" width="36" height="36" alt="" class="h-9 w-9 shrink-0 rounded-full object-contain">
                <span x-show="!collapsed || !isDesktop" class="text-sm font-semibold leading-5 text-ink">Alabang<span class="block text-xs font-normal">Health Center</span></span>
            </a>
            <button type="button" x-on:click="collapsed = !collapsed"
                    x-bind:aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    x-bind:title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    x-bind:aria-expanded="!collapsed" aria-controls="app-sidebar" data-sidebar-collapse
                    x-bind:class="collapsed ? 'absolute -right-3 top-10 border border-line bg-white shadow-sm' : ''"
                    class="hidden h-6 w-6 shrink-0 items-center justify-center rounded-full text-muted hover:bg-blush focus:outline-none focus:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-400 lg:flex">
                <svg x-bind:class="{ 'rotate-180': collapsed }" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 7-5 5 5 5" /></svg>
            </button>
            <button x-ref="sidebarClose" type="button" x-on:click="mobileOpen = false"
                    class="rounded-md p-2 text-muted hover:bg-blush focus:outline-none focus:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-400 lg:hidden"
                    aria-label="Close navigation menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg>
            </button>
        </div>
    </div>

    <nav class="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 py-3" aria-label="Main navigation">
        <p x-show="!collapsed || !isDesktop" class="mb-3 px-1 text-[10px] uppercase tracking-wide text-muted">Workspace</p>
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" label="Dashboard">
            <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />
        </x-sidebar-link>
        @if (auth()->user()?->is_active && auth()->user()?->hasAuthorizedRole())
            <x-sidebar-group id="patient-management" label="Patient Management" :active="request()->routeIs('patients.*', 'patient.information-form')">
                <x-slot:icon><circle cx="9" cy="8" r="3" /><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 4v2" /></x-slot:icon>
                <x-sidebar-link :href="route('patients.index')" :active="request()->routeIs('patients.*')" label="Patient Record" :nested="true">
                    <path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h4" />
                </x-sidebar-link>
                @can('queue-register')
                    <x-sidebar-link :href="route('patient.information-form')" :active="request()->routeIs('patient.information-form')" label="Patient Registration" :nested="true">
                        <circle cx="9" cy="7" r="3" /><path d="M3 21v-3a6 6 0 0 1 12 0v3M19 10v8m-4-4h8" />
                    </x-sidebar-link>
                @endcan
            </x-sidebar-group>
        @endif
        @canany(['queue-register', 'queue-view', 'queue-consult', 'follow-ups-manage', 'diagnoses-manage'])
            <x-sidebar-group id="medical" label="Medical" :active="request()->routeIs('patient.vital-signs-form', 'queues.vital-signs', 'medical-records.*', 'doctors-consultation.*', 'queues.consultation', 'follow-ups.*', 'diagnoses.*')">
                <x-slot:icon><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" /></x-slot:icon>
                @can('queue-register')
                    <x-sidebar-link :href="route('patient.vital-signs-form')" :active="request()->routeIs('patient.vital-signs-form', 'queues.vital-signs')" label="Vital Signs" :nested="true">
                        <path d="M3 12h4l3-8 4 16 3-8h4" />
                    </x-sidebar-link>
                @endcan
                @can('queue-view')
                    <x-sidebar-link :href="route('medical-records.index')" :active="request()->routeIs('medical-records.*')" label="Medical Records" :nested="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5" />
                    </x-sidebar-link>
                @endcan
                @can('queue-consult')
                    <x-sidebar-link :href="route('doctors-consultation.index')" :active="request()->routeIs('doctors-consultation.*', 'queues.consultation')" label="Doctor's Consultation" :nested="true">
                        <rect x="4" y="4" width="16" height="18" rx="2" /><path d="M9 2h6v4H9zM12 10v6M9 13h6" />
                    </x-sidebar-link>
                @endcan
                @can('follow-ups-manage')
                    <x-sidebar-link :href="route('follow-ups.index')" :active="request()->routeIs('follow-ups.*')" label="Follow-ups" :nested="true">
                        <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 11h18M8 15h3M8 18h5" />
                    </x-sidebar-link>
                @endcan
                @can('diagnoses-manage')
                    <x-sidebar-link :href="route('diagnoses.index')" :active="request()->routeIs('diagnoses.*')" label="Diagnosis Management" :nested="true">
                        <path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h4" />
                    </x-sidebar-link>
                @endcan
            </x-sidebar-group>
        @endcanany
        @can('queue-view')
            <x-sidebar-link :href="route('queues.index')" :active="request()->routeIs('queues.*') && ! request()->routeIs('queues.consultation', 'queues.vital-signs')" label="Queue Management">
                <path d="M9 6h12M9 12h12M9 18h12M3 6h.01M3 12h.01M3 18h.01" />
            </x-sidebar-link>
        @endcan
        @can('manage-accounts')
            <x-sidebar-link :href="route('users-roles.index')" :active="request()->routeIs('users-roles.*')" label="Users & Roles">
                <circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 7h6m-3-3v6M17 16h5m-5 4h5"/>
            </x-sidebar-link>
        @endcan
        <div x-show="!collapsed || !isDesktop" class="!mt-8 rounded-lg bg-blush px-3 py-4 text-xs">
            <svg class="mb-3 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 3 8 4v5c0 5-8 9-8 9s-8-4-8-9V7zM8 12l3 3 5-5"/></svg>
            <p class="font-semibold">Patient privacy first</p>
            <p class="mt-2 text-[11px] leading-relaxed text-muted">Access only the records needed for your assigned work.</p>
        </div>
    </nav>

    <div class="shrink-0 space-y-1 px-4 pb-6 pt-3">
        <a href="{{ route('profile.edit') }}" wire:navigate x-on:click="mobileOpen = false"
           aria-label="User profile" title="{{ Auth::user()->name }} — Profile"
           @if (request()->routeIs('profile.*')) aria-current="page" @endif
           @class(['flex items-center gap-3 rounded-lg px-2 py-3 text-xs transition-colors focus:outline-none focus:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-400',
               'bg-gray-100 text-ink font-semibold' => request()->routeIs('profile.*'),
               'text-ink hover:bg-gray-50' => ! request()->routeIs('profile.*')])>
            <span x-show="collapsed && isDesktop" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blush font-semibold">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
            <span x-show="!collapsed || !isDesktop" class="min-w-0">
                <span class="block truncate font-semibold">{{ Auth::user()->name }}</span>
                <span class="mt-1 block text-[11px] text-muted">{{ Auth::user()->role }}</span>
                <span class="sr-only">{{ Auth::user()->email }}</span>
            </span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" aria-label="Log Out" title="Log Out"
                    class="flex w-full items-center gap-3 rounded-lg px-2 py-3 text-xs text-ink transition-colors hover:bg-blush focus:outline-none focus:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-400">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" /></svg>
                <span x-show="!collapsed || !isDesktop">Secure sign out</span>
            </button>
        </form>
    </div>
</aside>
