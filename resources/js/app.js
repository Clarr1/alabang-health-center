import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;

if (window.livewireScriptConfig) {
    Livewire.start();
} else {
    Alpine.start();
}