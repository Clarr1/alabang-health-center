<?php

namespace App\Livewire;

use App\Models\SurveillanceSetting;
use App\Services\SurveillanceAdministration;
use App\Services\SurveillanceSimulation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detection Settings')]
class DetectionSettings extends Component
{
    public string $sensitivity = 'balanced';

    public bool $reviewed = false;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $simulation = null;

    public function mount(): void
    {
        Gate::authorize('surveillance-manage');
        $this->sensitivity = SurveillanceSetting::sensitivity();
    }

    public function save(): void
    {
        app(SurveillanceAdministration::class)->saveSensitivity($this->sensitivity, $this->reviewed);
        $this->reviewed = false;
        $this->simulation = null;
        session()->flash('surveillance-status', 'Detection settings saved. The next detection will use this sensitivity; previous results are preserved.');
    }

    public function testDetection(): void
    {
        Gate::authorize('surveillance-manage');
        $this->simulation = app(SurveillanceSimulation::class)->run();
    }

    public function render(): View
    {
        Gate::authorize('surveillance-manage');

        return view('livewire.detection-settings', [
            'settings' => SurveillanceSetting::current(),
            'profiles' => config('surveillance.sensitivities'),
        ]);
    }
}
