import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

Alpine.data('sidebarNavigation', () => ({
    collapsed: false,
    mobileOpen: false,
    isDesktop: window.innerWidth >= 1024,

    init() {
        try {
            this.collapsed = window.localStorage.getItem('ahc.sidebar.collapsed') === 'true';
        } catch {
            this.collapsed = false;
        }

        this.$watch('collapsed', (value) => {
            try {
                window.localStorage.setItem('ahc.sidebar.collapsed', String(value));
            } catch {
                // Navigation remains usable when browser storage is unavailable.
            }
        });
    },
}));

window.Alpine = Alpine;

if (window.livewireScriptConfig) {
    Livewire.start();
} else {
    Alpine.start();
}