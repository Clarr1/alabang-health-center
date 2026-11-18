<div class="space-y-5">
    <section aria-label="Surveillance summary" class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <a href="{{ route('surveillance.alerts') }}" wire:navigate class="patient-card group relative overflow-hidden transition-colors hover:border-pink-300">
            <div class="absolute inset-y-0 left-0 w-1 bg-brand-pink" aria-hidden="true"></div>
            <div class="flex items-center justify-between gap-3"><h2 class="text-xs font-semibold text-muted">Active alerts and signals</h2><span class="rounded-lg bg-pink-50 p-2 max-sm:hidden text-pink-500"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 8v5m0 3h.01M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span></div>
            <p class="mt-3 text-3xl font-semibold tracking-tight">{{ $activeAlerts }}</p><p class="mt-2 text-xs text-muted">{{ $awaitingReview }} {{ $awaitingReview === 1 ? 'alert' : 'alerts' }} requiring review <span aria-hidden="true">·</span> All categories</p>
        </a>
        <article class="patient-card relative overflow-hidden">
            <div class="absolute inset-y-0 left-0 w-1 bg-brand-blue" aria-hidden="true"></div>
            <div class="flex items-center justify-between gap-3"><h2 class="text-xs font-semibold text-muted">Qualifying cases · 48 hours</h2><span class="rounded-lg bg-sky-50 p-2 max-sm:hidden text-sky-600"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M9 3h6v4H9zM9 5H6a1 1 0 0 0-1 1v14h14V6a1 1 0 0 0-1-1h-3M8 11h8m-8 4h5"/></svg></span></div>
            <p class="mt-3 text-3xl font-semibold tracking-tight">{{ $qualifyingCases ?? '—' }}</p><p class="mt-2 text-xs text-muted">Counted once across the selected categories.</p>
        </article>
        <article class="patient-card">
            <div class="flex items-center justify-between gap-3"><h2 class="text-xs font-semibold text-muted">Monitored syndrome categories</h2><span class="rounded-lg bg-blush p-2 max-sm:hidden text-muted"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span></div>
            <p class="mt-3 text-3xl font-semibold tracking-tight">{{ $monitoredCategories }}</p><p class="mt-2 text-xs text-muted">Currently enabled across Barangay Alabang.</p>
        </article>
        <article class="patient-card">
            <div class="flex items-center justify-between gap-3"><h2 class="text-xs font-semibold text-muted">Categories reaching minimum</h2><span class="rounded-lg bg-amber-50 p-2 max-sm:hidden text-amber-600"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 20h16M7 17V9m5 8V5m5 12v-6M3 8h18"/></svg></span></div>
            <p class="mt-3 text-3xl font-semibold tracking-tight">{{ $thresholdsReached ?? '—' }}</p><p class="mt-2 text-xs text-muted">In this snapshot. Statistical review still applies.</p>
        </article>
    </section>

    <section class="patient-card space-y-4" aria-label="Overview filters">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-sm font-semibold">Monitoring scope</h2><p class="mt-1 text-xs text-muted">Choose a syndrome and detection-window end dates. Cards and comparisons use the latest saved check in that range.</p></div><span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-800">Barangay Alabang only</span></div>
        <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><label for="overview-syndrome" class="patient-label">Syndrome category</label><select id="overview-syndrome" wire:model.live="syndromeFilter" class="patient-input w-full"><option value="">All syndrome categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            <div><label for="overview-from" class="patient-label">Detection window ends from</label><input id="overview-from" type="date" wire:model.live="from" class="patient-input w-full"></div>
            <div><label for="overview-to" class="patient-label">Detection window ends through</label><input id="overview-to" type="date" wire:model.live="to" class="patient-input w-full"></div>
            <button type="button" wire:click="clearFilters" class="patient-button">Reset filters</button>
        </div>
        @if ($latest)
            <div class="flex flex-wrap items-center justify-between gap-x-5 gap-y-2 border-t border-line pt-3 text-xs">
                <p class="font-medium">48-hour window: {{ $latest->window_start->setTimezone('Asia/Manila')->format('d M, H:i') }} → {{ $latest->window_end->setTimezone('Asia/Manila')->format('d M Y, H:i') }} PHT</p>
                <p class="text-muted">Check #{{ $latest->id }} · {{ ucfirst($latest->source) }} · Saved {{ $latest->finished_at?->setTimezone('Asia/Manila')->format('d M, H:i') }} PHT</p>
            </div>
        @endif
    </section>

    @if ($latest && $results->isNotEmpty())
        <div class="space-y-5" wire:key="overview-charts-{{ hash('sha256', json_encode($overviewCharts)) }}" x-data="surveillanceOverviewCharts(@js($overviewCharts))">
            <p x-show="failed" x-cloak role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Charts could not be loaded. Exact saved values are available in the tables below.</p>
            <div class="grid items-start gap-5 xl:grid-cols-3">
                <section id="overview-trend-chart" class="patient-card min-w-0 scroll-mt-6 xl:col-span-2" aria-label="Syndrome trend chart">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h2 class="text-base font-semibold">Case pattern over time</h2><p class="mt-1 text-xs text-muted">{{ $focusedResult->syndrome_name }} · Up to 24 saved checks</p></div>
                        <div class="w-full sm:w-56"><label for="overview-trend-category" class="sr-only">Trend chart syndrome</label><select id="overview-trend-category" wire:model.live="trendCategoryId" class="patient-input w-full text-xs"><option value="">Priority syndrome</option>@foreach ($results as $result)<option value="{{ $result->syndrome_category_id }}">{{ $result->syndrome_name }}</option>@endforeach</select></div>
                    </div>
                    @if ($trendPoints->isNotEmpty())
                        <div class="relative mt-5 h-72 w-full sm:h-80" wire:ignore><canvas x-ref="trend" role="img" aria-label="Observed cases, historical expected cases, and saved minimums for {{ $focusedResult->syndrome_name }}">Exact values appear in the trend data table below.</canvas></div>
                        <p x-show="loading" class="text-xs text-muted" role="status">Loading chart...</p>
                        <p class="mt-3 text-xs leading-relaxed text-muted">Each point is a rolling 48-hour count; overlapping windows must not be added together. Missing historical comparisons remain gaps.</p>
                        <details class="mt-3 text-xs"><summary class="cursor-pointer font-medium text-muted">View exact trend values</summary><div class="mt-3 max-h-56 overflow-auto"><table class="w-full text-left"><thead class="bg-blush"><tr><th class="p-2">Window end (PHT)</th><th class="p-2">Observed</th><th class="p-2">Expected</th><th class="p-2">Saved minimum</th></tr></thead><tbody>@foreach ($trendPoints as $point)<tr class="border-b border-line"><td class="p-2">{{ $point->run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }}</td><td class="p-2">{{ $point->observed }}</td><td class="p-2">{{ $point->expected !== null ? number_format($point->expected, 2) : 'Not enough data' }}</td><td class="p-2">{{ $point->calculation['minimum_case_threshold'] ?? 'Not recorded' }}</td></tr>@endforeach</tbody></table></div></details>
                    @else<p class="mt-5 rounded-lg bg-blush p-5 text-sm text-muted">No saved trend points match this selection. Adjust the date range to see earlier checks.</p>@endif
                </section>
                <aside class="patient-card space-y-4" aria-label="Statistical interpretation">
                    <div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-brand-pink" aria-hidden="true"></span><h2 class="text-sm font-semibold">What this pattern means</h2></div>
                    <p class="text-xs font-medium text-muted">{{ $focusedResult->syndrome_name }}</p>
                    <p class="text-xl font-semibold tracking-tight">{{ $focusedResult->observed }} observed <span class="font-normal text-muted">/</span> {{ $focusedResult->expected !== null ? number_format($focusedResult->expected, 1) : '—' }} expected</p>
                    <x-surveillance-outcome :outcome="$focusedResult->outcome" :threshold-reached="$focusedResult->calculation['threshold_reached'] ?? false" />
                    @if ($focusedResult->expected !== null && $focusedResult->expected > 0)<p class="text-sm leading-relaxed">{{ number_format($focusedResult->observed / $focusedResult->expected, 1) }}× the historical expected count for this 48-hour window.</p>@endif
                    <dl class="grid grid-cols-2 gap-3 border-y border-line py-4 text-xs"><div><dt class="text-muted">Saved minimum</dt><dd class="mt-1 text-base font-semibold">{{ $focusedResult->calculation['minimum_case_threshold'] ?? 'Not recorded' }}</dd></div><div><dt class="text-muted">Monitoring period</dt><dd class="mt-1 text-base font-semibold">48 hours</dd></div></dl>
                    <p class="text-sm leading-relaxed text-muted">{{ match ($focusedResult->outcome) { 'Alert' => isset($focusedResult->calculation['minimum_case_threshold']) ? 'The case minimum was reached and the historical comparison found an unusual increase. Healthcare personnel should review the related alert.' : 'The historical comparison found an unusual increase. No minimum was recorded with this older result. Healthcare personnel should review the related alert.', 'Signal' => 'An unusual increase was found below the case minimum. This separate safeguard still needs healthcare review.', 'Watch' => 'The increase warrants closer monitoring, but does not meet the statistical alert criteria.', 'Normal' => 'The historical comparison did not identify a statistically unusual increase. Continue routine monitoring.', default => 'There is not enough reliable historical information for a statistical conclusion. Review approved definitions and record completeness.' } }}</p>
                    <p class="rounded-lg bg-blush p-3 text-xs leading-relaxed text-muted">A syndrome signal does not confirm a disease or an outbreak. Alerts stay inside this application.</p>
                    <a href="{{ route('surveillance.alerts') }}" wire:navigate class="patient-button w-full bg-brand-pink">View surveillance alerts <span aria-hidden="true">→</span></a>
                </aside>
            </div>
            <section class="patient-card min-w-0" aria-label="Category case comparison">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-base font-semibold">Cases across syndrome categories</h2><p class="mt-1 text-xs text-muted">Observed and historical expected counts in the selected snapshot.</p></div><span class="rounded-full bg-blush px-3 py-1 text-xs text-muted">{{ $results->count() }} {{ $results->count() === 1 ? 'category' : 'categories' }}</span></div>
                <div class="relative mt-5 w-full" style="height: {{ max(220, $results->count() * 68 + 70) }}px" wire:ignore><canvas x-ref="comparison" role="img" aria-label="Observed versus historical expected case counts by syndrome category">Exact values appear in the syndrome monitoring table below.</canvas></div>
                <p class="mt-3 text-xs text-muted">Pink indicates an alert; amber indicates a watch or below-minimum signal. A patient may qualify for more than one syndrome, so category counts are not additive.</p>
            </section>
        </div>
    @else
        <section class="patient-card py-10 text-center sm:py-14" aria-label="No surveillance results">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-sky-50 text-sky-600"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 20h16M6 16v-4m6 4V5m6 11V9"/></svg></div>
            <h2 class="text-base font-semibold">{{ $latest ? 'No syndrome results match this selection' : 'No completed checks in this view' }}</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-muted">{{ $from || $to || $syndromeFilter ? 'Try another syndrome or widen the date range. Saved results and alert history remain available.' : 'Approve syndrome categories, save monitoring thresholds, and verify patient residence before checking for case increases. Charts will appear when a completed check is available.' }}</p>
            @if ($from || $to || $syndromeFilter)<button type="button" wire:click="clearFilters" class="patient-button mt-5 bg-brand-blue">Reset filters</button>@endif
        </section>
    @endif

    <section class="patient-card space-y-4" aria-label="Syndrome monitoring registry">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-base font-semibold">Syndrome monitoring</h2><p class="mt-1 text-xs text-muted">Minimums trigger a statistical check; reaching one does not confirm an outbreak.</p></div><a href="{{ route('surveillance.history') }}" wire:navigate class="text-xs font-semibold text-sky-700 underline-offset-4 hover:underline">View surveillance history →</a></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-blush text-xs text-muted"><tr><th class="rounded-l-lg px-3 py-3 font-semibold">Syndrome category</th><th class="px-3 py-3 font-semibold">Current cases</th><th class="px-3 py-3 font-semibold">Minimum threshold</th><th class="px-3 py-3 font-semibold">Historical expected</th><th class="px-3 py-3 font-semibold">Detection status</th><th class="rounded-r-lg px-3 py-3 font-semibold">Action</th></tr></thead><tbody class="divide-y divide-line">
            @forelse ($results as $result)
                <tr wire:key="overview-result-{{ $result->id }}"><td class="min-w-[160px] px-3 py-4 font-medium">{{ $result->syndrome_name }}</td><td class="px-3 py-4 text-base font-semibold">{{ $result->observed }}</td><td class="px-3 py-4">{{ $result->calculation['minimum_case_threshold'] ?? 'Not recorded' }}<span class="mt-1 block text-xs text-muted">{{ isset($result->calculation['threshold_reached']) ? ($result->calculation['threshold_reached'] ? 'Minimum reached' : 'Below minimum') : 'Legacy result' }}</span></td><td class="px-3 py-4">{{ $result->expected !== null ? number_format($result->expected, 2) : 'Not enough data' }}</td><td class="min-w-[170px] px-3 py-4"><x-surveillance-outcome :outcome="$result->outcome" :threshold-reached="$result->calculation['threshold_reached'] ?? false" /></td><td class="px-3 py-4"><button type="button" wire:click="$set('trendCategoryId', '{{ $result->syndrome_category_id }}')" x-on:click="document.getElementById('overview-trend-chart')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' })" class="patient-button whitespace-nowrap text-xs">View trend</button></td></tr>
            @empty<tr><td colspan="6" class="px-3 py-6 text-sm text-muted">No saved syndrome results to display.</td></tr>@endforelse
        </tbody></table></div>
        @if ($latest)<p class="text-xs text-muted">{{ $latest->eligible_cases }} unique eligible patients with valid encounters in this window, across all syndromes. Qualifying cases count illness episodes. The window end is excluded.</p>@endif
    </section>

    <section class="patient-card space-y-4" aria-label="Recent surveillance activity">
        <div class="flex items-center justify-between gap-3"><div><h2 class="text-base font-semibold">Recent surveillance alerts</h2><p class="mt-1 text-xs text-muted">Latest alerts matching the selected syndrome and detection dates.</p></div><a href="{{ route('surveillance.alerts') }}" wire:navigate class="shrink-0 text-xs font-semibold text-sky-700 underline-offset-4 hover:underline">View all alerts →</a></div>
        <div class="divide-y divide-line">
            @forelse ($recentAlerts as $alert)
                <article class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0" wire:key="overview-alert-{{ $alert->id }}"><div class="min-w-0"><p class="text-sm font-semibold">{{ $alert->result->syndrome_name }}</p><p class="mt-1 text-xs text-muted">{{ $alert->reference }} · {{ $alert->detected_at->setTimezone('Asia/Manila')->format('d M Y, H:i') }} PHT</p><p class="mt-1 text-xs text-muted">{{ $alert->result->outcome === 'Signal' ? 'Below-minimum statistical signal' : 'Statistical alert' }} · {{ $alert->result->observed }} observed / {{ $alert->result->expected !== null ? number_format($alert->result->expected, 1) : '—' }} expected</p></div><div class="flex items-center gap-3"><span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-pink-50 text-pink-700' => in_array($alert->status, ['New', 'Escalated']), 'bg-sky-50 text-sky-800' => $alert->status === 'Under Review', 'bg-gray-100 text-gray-600' => ! in_array($alert->status, ['New', 'Escalated', 'Under Review'])])>{{ $alert->status }}</span>@can('surveillance-review')<button type="button" wire:click="openAlert({{ $alert->id }})" class="patient-button bg-brand-pink text-xs">Review alert</button>@endcan</div></article>
            @empty<div class="rounded-lg border border-dashed border-line p-6 text-center"><p class="text-sm font-medium">No alerts match this view</p><p class="mt-1 text-xs text-muted">New statistical alerts and their review status will appear here. Historical records remain in Surveillance Alerts.</p></div>@endforelse
        </div>
    </section>
</div>
