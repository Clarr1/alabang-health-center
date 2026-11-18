<?php

namespace App\Services;

use App\Models\SurveillanceAudit;
use App\Models\SurveillanceSetting;
use App\Models\Symptom;
use App\Models\SyndromeCategory;
use App\Models\SyndromeRuleVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SurveillanceAdministration
{
    /** @param array{name: string, description: ?string, is_active: bool, symptom_ids: array<int, int>, minimum_matches: int, applies_from: string, rationale: string} $data */
    public function saveDefinition(array $data, ?int $categoryId = null): SyndromeRuleVersion
    {
        Gate::authorize('surveillance-manage');
        Validator::make($data, [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/u', Rule::unique('syndrome_categories', 'name')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'symptom_ids' => ['required', 'array', 'min:1', 'max:50'],
            'symptom_ids.*' => ['integer', 'distinct', Rule::exists('symptoms', 'id')->where('is_active', true)],
            'minimum_matches' => ['required', 'integer', 'min:1', 'max:'.count($data['symptom_ids'])],
            'applies_from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rationale' => ['required', 'string', 'min:10', 'max:5000', 'not_regex:/^\s*$/u'],
        ])->validate();

        return DB::transaction(function () use ($data, $categoryId): SyndromeRuleVersion {
            $category = $categoryId ? SyndromeCategory::lockForUpdate()->findOrFail($categoryId) : new SyndromeCategory;
            $category->fill(collect($data)->only(['name', 'description', 'is_active'])->all())->save();
            $rule = $category->rules()->create([
                'version' => (int) $category->rules()->max('version') + 1,
                'minimum_matches' => $data['minimum_matches'],
                'applies_from' => CarbonImmutable::parse($data['applies_from'], 'Asia/Manila')->utc(),
                'rationale' => $data['rationale'],
            ]);
            $symptoms = Symptom::whereIn('id', $data['symptom_ids'])->where('is_active', true)->lockForUpdate()->get();
            if ($symptoms->count() !== count($data['symptom_ids'])) {
                throw ValidationException::withMessages(['symptomIds' => 'A selected symptom is no longer active.']);
            }
            $rule->symptoms()->attach($symptoms->mapWithKeys(fn (Symptom $symptom): array => [$symptom->id => ['symptom_name' => $symptom->name]])->all());
            $this->audit('Definition drafted', ['category_id' => $category->id, 'rule_id' => $rule->id, 'definition' => $data]);

            return $rule;
        });
    }

    public function approve(int $ruleId): void
    {
        Gate::authorize('surveillance-approve');
        DB::transaction(function () use ($ruleId): void {
            $rule = SyndromeRuleVersion::lockForUpdate()->findOrFail($ruleId);
            if ($rule->approved_at !== null) {
                return;
            }
            if ($rule->symptoms()->where('is_active', false)->exists() || $rule->symptoms()->count() < $rule->minimum_matches) {
                throw ValidationException::withMessages(['definition' => 'Review the symptom mappings before approval.']);
            }
            if (SyndromeRuleVersion::where('syndrome_category_id', $rule->syndrome_category_id)->where('version', '>', $rule->version)->whereNotNull('approved_at')->exists()) {
                throw ValidationException::withMessages(['definition' => 'A newer version is already approved.']);
            }
            $rule->update(['approved_at' => now(), 'approved_by' => auth()->id()]);
            $this->audit('Definition clinically approved', ['rule_id' => $rule->id, 'version' => $rule->version]);
        });
    }

    public function toggle(int $categoryId): void
    {
        Gate::authorize('surveillance-manage');
        DB::transaction(function () use ($categoryId): void {
            $category = SyndromeCategory::lockForUpdate()->findOrFail($categoryId);
            $category->update(['is_active' => ! $category->is_active]);
            $this->audit('Category activation changed', ['category_id' => $categoryId, 'is_active' => $category->is_active]);
        });
    }

    public function saveSensitivity(string $sensitivity, bool $reviewed): void
    {
        Gate::authorize('surveillance-manage');
        Validator::make(['sensitivity' => $sensitivity, 'reviewed' => $reviewed], [
            'sensitivity' => ['required', Rule::in(array_keys(config('surveillance.sensitivities')))],
            'reviewed' => ['accepted'],
        ])->validate();

        $profile = config('surveillance.sensitivities.'.$sensitivity);
        $this->saveSettings(array_replace(SurveillanceSetting::current(), [
            'alpha' => $profile['alpha'], 'watch_alpha' => $profile['watch_alpha'], 'settings_reviewed' => true,
        ]));
    }

    /** @param array{category_id: mixed, minimum_cases: mixed, is_active: bool} $data */
    public function saveThreshold(array $data): void
    {
        Gate::authorize('surveillance-manage');
        $validated = Validator::make($data, [
            'category_id' => ['required', 'integer', 'exists:syndrome_categories,id'],
            'minimum_cases' => ['required', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ])->validate();

        DB::transaction(function () use ($validated): void {
            $setting = SurveillanceSetting::lockForUpdate()->findOrFail(1);
            $category = SyndromeCategory::lockForUpdate()->findOrFail($validated['category_id']);
            $settings = SurveillanceSetting::current();
            $before = ['minimum_cases' => SurveillanceSetting::minimumCases($category->id, $settings), 'is_active' => $category->is_active];
            $settings['category_thresholds'][$category->id] = (int) $validated['minimum_cases'];
            $settings['settings_reviewed'] = true;
            $setting->update(['values' => $settings, 'updated_by' => auth()->id()]);
            $category->update(['is_active' => $validated['is_active']]);
            $this->audit('Category monitoring threshold changed', [
                'category_id' => $category->id, 'syndrome_name' => $category->name,
                'before' => $before, 'after' => ['minimum_cases' => (int) $validated['minimum_cases'], 'is_active' => $category->is_active],
            ]);
        });
    }

    /** @param array<string, mixed> $values */
    public function saveSettings(array $values): void
    {
        Gate::authorize('surveillance-manage');
        $validated = Validator::make($values, [
            'window_hours' => ['required', 'integer', Rule::in([48])],
            'baseline_weeks' => ['required', 'integer', 'min:4', 'max:104'],
            'minimum_periods' => ['required', 'integer', 'min:4', 'max:'.$values['baseline_weeks']],
            'minimum_attendance' => ['required', 'integer', 'min:1', 'max:10000'],
            'minimum_coverage' => ['required', 'numeric', 'between:0.5,1'],
            'episode_gap_hours' => ['required', 'integer', 'min:24', 'max:336'],
            'alpha' => ['required', 'numeric', 'between:0.000001,0.05'],
            'watch_alpha' => ['required', 'numeric', 'gte:alpha', 'max:0.2'],
            'overdispersion_ratio' => ['required', 'numeric', 'between:1.1,3'],
            'frequency_minutes' => ['required', 'integer', Rule::in([15, 30, 60, 120, 360, 720, 1440])],
            'settings_reviewed' => ['accepted'],
            'category_thresholds' => ['sometimes', 'array'],
            'category_thresholds.*' => ['required', 'integer', 'min:1', 'max:10000'],
        ])->validate();
        DB::transaction(function () use ($validated): void {
            $previous = SurveillanceSetting::current();
            $validated['category_thresholds'] ??= $previous['category_thresholds'];
            SurveillanceSetting::updateOrCreate(['id' => 1], ['values' => $validated, 'updated_by' => auth()->id()]);
            $this->audit('Statistical configuration reviewed for development testing', ['before' => $previous, 'after' => $validated]);
        });
    }

    /** @param array<string, mixed> $details */
    private function audit(string $action, array $details): void
    {
        SurveillanceAudit::create(['actor_id' => auth()->id(), 'action' => $action, 'details' => $details]);
    }
}
