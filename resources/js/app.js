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

Alpine.data('bloodPressureMeasurement', (pressure) => ({
    pressure,

    get systolic() {
        return String(this.pressure || '').split('/')[0] || '';
    },

    set systolic(value) {
        const diastolic = this.diastolic;
        this.pressure = value || diastolic ? value + '/' + diastolic : '';
    },

    get diastolic() {
        return String(this.pressure || '').split('/')[1] || '';
    },

    set diastolic(value) {
        const systolic = this.systolic;
        this.pressure = systolic || value ? systolic + '/' + value : '';
    },
}));

Alpine.data('sidebarGroup', () => ({
    open: false,

    init() {
        this.syncActiveGroup();
    },

    syncActiveGroup() {
        this.open = this.$el.dataset.active === 'true';
    },

    toggleGroup() {
        if (this.collapsed && this.isDesktop) {
            this.expandGroup();
            return;
        }

        this.open = !this.open;
    },

    expandGroup(focusFirst = false) {
        if (this.isDesktop) {
            this.collapsed = false;
        }
        this.open = true;

        if (focusFirst) {
            this.$nextTick(() => this.$refs.children.querySelector('a')?.focus());
        }
    },

    closeGroup() {
        this.open = false;
        this.$refs.toggle.focus();
    },
}));

window.Alpine = Alpine;

if (window.livewireScriptConfig) {
    Livewire.start();
} else {
    Alpine.start();
}
