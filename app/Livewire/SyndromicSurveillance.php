<?php

namespace App\Livewire;

use App\Models\SurveillanceAlert;
use App\Models\SurveillanceResult;
use App\Models\SurveillanceRun;
use App\Models\SyndromeCategory;
use App\Models\User;
use App\Services\SurveillanceEngine;
use App\Services\SurveillanceReview;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
#[Title('Syndromic Surveillance')]
class SyndromicSurveillance extends Component
{
    use WithPagination;

    #[Locked]
    public string $section = 'overview';

    public string $syndromeFilter = '';

    public string $trendCategoryId = '';

    public string $statusFilter = '';

    public string $from = '';

    public string $to = '';

    #[Locked]
    public ?int $selectedAlertId = null;

    #[Locked]
    public int $reviewVersion = 0;

    public string $reviewStatus = 'Under Review';

    public string $notes = '';

    public string $outcome = '';

    public string $reviewerId = '';

    public function mount(): void
    {
        Gate::authorize('surveillance-view');
        $this->section = match (request()->route()?->getName()) {
            'surveillance.alerts' => 'alerts',
            'surveillance.history' => 'history',
            default => 'overview',
        };
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'syndromeFilter' => ['nullable', 'integer', 'exists:syndrome_categories,id'],
            'trendCategoryId' => ['nullable', 'integer', 'exists:syndrome_categories,id'],
            'statusFilter' => ['nullable', Rule::in(SurveillanceAlert::STATUSES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->validDate($this->from), ['after_or_equal:from'])],
        ];
    }

    public function updated(string $property): void
    {
        Gate::authorize('surveillance-view');
        if (array_key_exists($property, $this->rules())) {
            if ($property === 'syndromeFilter') {
                $this->trendCategoryId = '';
            }
            $this->resetPage();
            $this->validateOnly($property);
        }
    }

    public function clearFilters(): void
    {
        Gate::authorize('surveillance-view');
        $this->reset('syndromeFilter', 'trendCategoryId', 'statusFilter', 'from', 'to');
        $this->resetPage();
        $this->resetValidation();
    }

    public function detect(): void
    {
        Gate::authorize('surveillance-manage');
        try {
            $run = app(SurveillanceEngine::class)->run('manual');
            session()->flash('surveillance-status', "Case increase check #{$run->id}: {$run->status}.");
        } catch (Throwable $exception) {
            $this->addError('detection', 'Detection failed. The failure was recorded; review the execution log.');
        }
    }

    public function openAlert(int $id): void
    {
        Gate::authorize('surveillance-review');
        $alert = SurveillanceAlert::whereNotNull('surveillance_result_id')->findOrFail($id);
        $this->selectedAlertId = $id;
        $this->reviewVersion = $alert->version;
        $this->reviewStatus = 'Under Review';
        $this->reviewerId = (string) ($alert->assigned_to ?? auth()->id());
        $this->notes = '';
        $this->outcome = '';
        $this->resetValidation();
        $this->dispatch('surveillance-alert-opened');
    }

    public function closeAlert(): void
    {
        $this->selectedAlertId = null;
    }

    public function saveReview(): void
    {
        Gate::authorize('surveillance-review');
        abort_if($this->selectedAlertId === null, 404);
        $this->validate(['reviewerId' => ['nullable', 'integer']]);
        $alert = app(SurveillanceReview::class)->record(
            $this->selectedAlertId, $this->reviewVersion, $this->reviewStatus, $this->notes,
            $this->outcome ?: null, $this->reviewerId !== '' ? (int) $this->reviewerId : null,
        );
        $this->reviewVersion = $alert->version;
        $this->reset('notes', 'outcome');
        $this->reviewStatus = app(SurveillanceReview::class)->allowedStatuses($alert->status)[0];
        session()->flash('surveillance-status', 'Investigation action saved to the alert history.');
    }

    private function dateFilters(Builder $query, string $column): Builder
    {
        if ($this->validDate($this->from)) {
            $query->where($column, '>=', CarbonImmutable::parse($this->from, 'Asia/Manila')->utc());
        }
        if ($this->validDate($this->to)) {
            $query->where($column, '<', CarbonImmutable::parse($this->to, 'Asia/Manila')->addDay()->utc());
        }

        return $query;
    }

    private function validDate(string $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) === 1 && CarbonImmutable::hasFormat($date, 'Y-m-d');
    }

    public function render(): View
    {
        Gate::authorize('surveillance-view');
        $summaryColumns = ['id', 'surveillance_run_id', 'syndrome_category_id', 'syndrome_name', 'observed', 'expected', 'outcome',
            DB::raw("json_object('minimum_case_threshold', json_extract(calculation, '$.minimum_case_threshold'), 'threshold_reached', json_extract(calculation, '$.threshold_reached')) as calculation"),
        ];
        $latestQuery = SurveillanceRun::where('status', 'Completed');
        if ($this->section === 'overview') {
            $latestQuery = $this->dateFilters($latestQuery, 'window_end');
        }
        $latest = $latestQuery->latest('window_end')->latest('id')->with(['results' => fn ($query) => $query->select($summaryColumns)])->first();
        $results = $latest?->results->when(ctype_digit($this->syndromeFilter), fn ($rows) => $rows->where('syndrome_category_id', $this->syndromeFilter)) ?? collect();
        $alerts = $this->dateFilters(SurveillanceAlert::whereNotNull('surveillance_result_id'), 'detected_at')
            ->when(ctype_digit($this->syndromeFilter), fn (Builder $query): Builder => $query->where('syndrome_category_id', $this->syndromeFilter))
            ->when(in_array($this->statusFilter, SurveillanceAlert::STATUSES, true), fn (Builder $query): Builder => $query->where('status', $this->statusFilter));
        $selected = $this->selectedAlertId !== null && Gate::allows('surveillance-review')
            ? SurveillanceAlert::with('result.run', 'result.cases.record.patient', 'reviews.user', 'reviewer')->findOrFail($this->selectedAlertId) : null;
        $trends = SurveillanceResult::select($summaryColumns)->with('run')->whereHas('run', fn (Builder $query): Builder => $this->dateFilters($query->where('status', 'Completed'), 'window_end'))
            ->when(ctype_digit($this->syndromeFilter), fn (Builder $query): Builder => $query->where('syndrome_category_id', $this->syndromeFilter))
            ->latest('id')->limit(120)->get()->reverse()->groupBy('syndrome_name');
        $runs = SurveillanceRun::with(['results' => function (HasMany $results) use ($summaryColumns): void {
            $results->select($summaryColumns);
            if (ctype_digit($this->syndromeFilter)) {
                $results->where('syndrome_category_id', $this->syndromeFilter);
            }
        }]);

        $focusedResult = $results->firstWhere('syndrome_category_id', $this->trendCategoryId)
            ?? $results->sortByDesc(fn (SurveillanceResult $result): int => (match ($result->outcome) {
                'Alert' => 4, 'Signal' => 3, 'Watch' => 2, 'Insufficient Data' => 1, default => 0,
            }) * 100000 + $result->observed)->first();
        $trendPoints = $focusedResult ? $trends->flatten(1)->where('syndrome_category_id', $focusedResult->syndrome_category_id)
            ->sortBy(fn (SurveillanceResult $result): string => $result->run->window_end->format('Y-m-d H:i:s').sprintf('%020d', $result->id))->take(-24)->values() : collect();
        $overviewCharts = [
            'comparison' => [
                'labels' => $results->pluck('syndrome_name')->values()->all(),
                'observed' => $results->pluck('observed')->values()->all(),
                'expected' => $results->pluck('expected')->values()->all(),
                'outcomes' => $results->pluck('outcome')->values()->all(),
            ],
            'trend' => [
                'name' => $focusedResult?->syndrome_name,
                'labels' => $trendPoints->map(fn (SurveillanceResult $result): string => $result->run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i'))->all(),
                'observed' => $trendPoints->pluck('observed')->all(),
                'expected' => $trendPoints->pluck('expected')->all(),
                'minimum' => $trendPoints->map(fn (SurveillanceResult $result): ?int => $result->calculation['minimum_case_threshold'] ?? null)->all(),
                'outcomes' => $trendPoints->pluck('outcome')->all(),
            ],
        ];

        return view('livewire.syndromic-surveillance', [
            'latest' => $latest,
            'focusedResult' => $focusedResult, 'trendPoints' => $trendPoints, 'overviewCharts' => $overviewCharts,
            'recentAlerts' => $this->section === 'overview' ? (clone $alerts)->with(['result' => fn ($query) => $query->select($summaryColumns)])
                ->latest('detected_at')->latest('id')->limit(4)->get() : collect(),
            'qualifyingCases' => $latest ? DB::table('surveillance_result_cases')
                ->join('surveillance_classifications', 'surveillance_classifications.id', '=', 'surveillance_result_cases.surveillance_classification_id')
                ->whereIn('surveillance_result_id', $results->modelKeys())
                ->distinct()->count('medical_record_id') : null,
            'thresholdsReached' => $latest ? $results->filter(fn (SurveillanceResult $result): bool => (bool) ($result->calculation['threshold_reached'] ?? false))->count() : null,
            'monitoredCategories' => SyndromeCategory::where('is_active', true)->count(),
            'results' => $results,
            'alerts' => $alerts->with(['result' => fn ($query) => $query->select($summaryColumns), 'result.run', 'reviewer'])->latest('id')->paginate(10),
            'categories' => SyndromeCategory::orderBy('name')->get(), 'selected' => $selected,
            'reviewStatuses' => $selected ? app(SurveillanceReview::class)->allowedStatuses($selected->status) : [],
            'activeAlerts' => SurveillanceAlert::whereNotNull('surveillance_result_id')->whereIn('status', ['New', 'Under Review', 'Escalated'])->count(),
            'awaitingReview' => SurveillanceAlert::whereNotNull('surveillance_result_id')->whereIn('status', ['New', 'Escalated'])->count(),
            'reviewers' => Gate::allows('surveillance-review') ? User::where('is_active', true)->whereIn('role', ['Admin', 'Doctor'])->orderBy('name')->get() : collect(),
            'runs' => $this->section === 'history' && Gate::allows('surveillance-manage') ? $this->dateFilters($runs, 'window_end')->latest('id')->limit(20)->get() : collect(),
            'trends' => $trends,
            'legacyAlerts' => Gate::allows('surveillance-review') ? SurveillanceAlert::whereNull('surveillance_result_id')->latest('id')->limit(20)->get() : collect(),
        ]);
    }
}
