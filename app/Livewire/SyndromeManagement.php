<?php

namespace App\Livewire;

use App\Models\SurveillanceAudit;
use App\Models\SurveillanceSetting;
use App\Models\Symptom;
use App\Models\SyndromeCategory;
use App\Services\SurveillanceAdministration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Syndrome Definitions & Settings')]
class SyndromeManagement extends Component
{
    #[Locked]
    public ?int $categoryId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    /** @var array<int, int|string> */
    public array $symptomIds = [];

    public int $minimumMatches = 1;

    public string $appliesFrom = '';

    public string $rationale = '';

    /** @var array<string, mixed> */
    public array $settings = [];

    public function mount(): void
    {
        Gate::authorize('surveillance-approve');
        $this->appliesFrom = now('Asia/Manila')->toDateString();
        $this->settings = SurveillanceSetting::current();
        $this->settings['settings_reviewed'] = false;
    }

    public function edit(int $id): void
    {
        Gate::authorize('surveillance-manage');
        $category = SyndromeCategory::with('rules.symptoms')->findOrFail($id);
        $rule = $category->rules->sortByDesc('version')->first();
        $this->categoryId = $id;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->isActive = $category->is_active;
        $this->symptomIds = $rule?->symptoms->modelKeys() ?? [];
        $this->minimumMatches = $rule?->minimum_matches ?? 1;
        $this->appliesFrom = $rule?->applies_from->setTimezone('Asia/Manila')->toDateString() ?? now('Asia/Manila')->toDateString();
        $this->rationale = '';
        $this->resetValidation();
    }

    public function newDefinition(): void
    {
        Gate::authorize('surveillance-manage');
        $this->reset('categoryId', 'name', 'description', 'isActive', 'symptomIds', 'minimumMatches', 'rationale');
        $this->appliesFrom = now('Asia/Manila')->toDateString();
        $this->resetValidation();
    }

    public function saveDefinition(): void
    {
        Gate::authorize('surveillance-manage');
        app(SurveillanceAdministration::class)->saveDefinition([
            'name' => trim($this->name), 'description' => $this->description ?: null, 'is_active' => $this->isActive,
            'symptom_ids' => $this->symptomIds, 'minimum_matches' => $this->minimumMatches,
            'applies_from' => $this->appliesFrom, 'rationale' => $this->rationale,
        ], $this->categoryId);
        $this->newDefinition();
        session()->flash('surveillance-status', 'Definition saved as an unreviewed version. Approval is required before use.');
    }

    public function approve(int $ruleId): void
    {
        app(SurveillanceAdministration::class)->approve($ruleId);
        session()->flash('surveillance-status', 'Clinical approval recorded. Existing classifications remain unchanged.');
    }

    public function toggle(int $id): void
    {
        app(SurveillanceAdministration::class)->toggle($id);
    }

    public function saveSettings(): void
    {
        app(SurveillanceAdministration::class)->saveSettings($this->settings);
        $this->settings['settings_reviewed'] = false;
        session()->flash('surveillance-status', 'Statistical settings reviewed and saved for development testing.');
    }

    public function render(): View
    {
        Gate::authorize('surveillance-approve');

        return view('livewire.syndrome-management', [
            'categories' => SyndromeCategory::with(['rules' => fn ($query) => $query->latest('version'), 'rules.symptoms'])->orderBy('name')->get(),
            'symptoms' => Symptom::where('is_active', true)->orderBy('name')->get(),
            'audits' => Gate::allows('surveillance-manage') ? SurveillanceAudit::latest('id')->limit(20)->get() : collect(),
        ]);
    }
}
