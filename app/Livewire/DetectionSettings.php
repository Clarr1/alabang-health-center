<?php

namespace App\Livewire;

use App\Models\SurveillanceSetting;
use App\Models\SyndromeCategory;
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
    public string $categoryId = '';

    public string $minimumCases = '';

    public bool $isActive = false;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $simulation = null;

    public function mount(): void
    {
        Gate::authorize('surveillance-manage');
        $category = SyndromeCategory::orderBy('name')->first();
        if ($category !== null) {
            $this->categoryId = (string) $category->id;
            $this->updatedCategoryId();
        }
    }

    public function updatedCategoryId(): void
    {
        Gate::authorize('surveillance-manage');
        $category = SyndromeCategory::find($this->categoryId);
        $this->minimumCases = $category ? (string) SurveillanceSetting::minimumCases($category->id, SurveillanceSetting::current()) : '';
        $this->isActive = $category?->is_active ?? false;
        $this->simulation = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        app(SurveillanceAdministration::class)->saveThreshold([
            'category_id' => $this->categoryId, 'minimum_cases' => $this->minimumCases, 'is_active' => $this->isActive,
        ]);
        $this->simulation = null;
        session()->flash('surveillance-status', 'Monitoring settings saved. The next check will use this threshold; previous results are preserved.');
    }

    public function testDetection(): void
    {
        Gate::authorize('surveillance-manage');
        $this->validate(['categoryId' => ['required', 'integer', 'exists:syndrome_categories,id']]);
        $this->simulation = app(SurveillanceSimulation::class)->run((int) $this->categoryId);
    }

    public function render(): View
    {
        Gate::authorize('surveillance-manage');

        return view('livewire.detection-settings', [
            'settings' => SurveillanceSetting::current(),
            'categories' => SyndromeCategory::orderBy('name')->get(),
        ]);
    }
}
