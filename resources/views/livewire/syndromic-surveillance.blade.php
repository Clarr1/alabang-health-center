<div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-7 sm:px-7 lg:px-8" wire:poll.60s x-on:surveillance-alert-opened.window="$nextTick(() => document.getElementById('alert-investigation')?.focus())">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-xs font-semibold uppercase tracking-wide text-muted">Syndromic Surveillance</p><h1 class="mt-2 text-2xl font-semibold">{{ match ($section) { 'alerts' => 'Surveillance Alerts', 'history' => 'Surveillance History', default => 'Overview' } }}</h1><p class="mt-2 text-sm text-muted">Barangay Alabang, Muntinlupa City · All displayed times Asia/Manila</p></div>
        <div class="flex flex-wrap gap-2">
            @can('surveillance-manage')<button type="button" wire:click="detect" wire:confirm="Check eligible patient records from the last 48 hours? Results and internal alerts will be saved. For a simulated preview, use Detection Settings." wire:loading.attr="disabled" class="patient-button bg-brand-blue">Check for case increases</button>@endcan
        </div>
    </div>
    <x-surveillance-nav :active="match ($section) { 'alerts' => 'surveillance.alerts', 'history' => 'surveillance.history', default => 'surveillance.index' }" />
    @if ($section === 'overview')
        <p class="flex items-start gap-2 text-xs leading-relaxed text-muted"><span class="shrink-0 rounded bg-white px-2 py-0.5 font-semibold">Development</span><span>Unusual patterns require healthcare review. Signals do not confirm a disease or outbreak. Alerts remain internal.</span></p>
    @else
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Development surveillance · Signals identify unusual syndrome patterns requiring healthcare personnel review. They do not confirm a disease or an outbreak. Alerts remain inside this application.</p>
    @endif
    @if (session('surveillance-status'))<p role="status" class="patient-card text-sm">{{ session('surveillance-status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p wire:loading.delay role="status" class="text-xs text-muted">Updating surveillance information…</p>

    @if ($section !== 'overview')
    <section class="patient-card">
        <h2 class="font-semibold">Filter recorded results and alerts</h2><p class="mt-1 text-xs text-muted">Dates filter monitoring-window end dates and alert detection dates. Counts always use the saved rolling window.</p>
        <div class="mt-4 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div><label for="syndrome-filter" class="patient-label">Syndrome</label><select id="syndrome-filter" wire:model.live="syndromeFilter" class="patient-input w-full"><option value="">All syndromes</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            @if ($section === 'alerts')<div><label for="alert-status" class="patient-label">Alert status</label><select id="alert-status" wire:model.live="statusFilter" class="patient-input w-full"><option value="">All statuses</option>@foreach (\App\Models\SurveillanceAlert::STATUSES as $status)<option>{{ $status }}</option>@endforeach</select></div>@endif
            <div><label for="surveillance-from" class="patient-label">From date</label><input id="surveillance-from" type="date" wire:model.live="from" class="patient-input w-full"></div>
            <div><label for="surveillance-to" class="patient-label">To date</label><input id="surveillance-to" type="date" wire:model.live="to" class="patient-input w-full"></div>
            <button type="button" wire:click="clearFilters" class="patient-button">Clear filters</button>
        </div>
    </section>
    @endif
    @if ($section === 'overview')
        @include('livewire.syndromic-surveillance-overview')
    @endif
    @if ($section === 'history')
    <section class="patient-card space-y-4">
        <h2 class="text-lg font-semibold">Current and expected case trends</h2><p class="text-sm text-muted">Blue: current cases · Dashed gray: expected cases · Pink: alert · Amber: watch or below-minimum signal. Each point is a saved detection result. Rolling windows overlap; counts should not be added together.</p>
        <div class="grid gap-5 lg:grid-cols-2">
            @forelse ($trends as $name => $points)
                @php
                    $series = $points->values();
                    $historyChart = [
                        'trend' => [
                            'labels' => $series->map(fn ($point) => $point->run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i'))->all(),
                            'observed' => $series->pluck('observed')->all(),
                            'expected' => $series->pluck('expected')->all(),
                            'outcomes' => $series->pluck('outcome')->all(),
                        ],
                        'comparison' => ['labels' => []],
                    ];
                @endphp
                <figure class="min-w-0 rounded-lg border border-line p-4" wire:key="history-chart-{{ hash('sha256', $name.json_encode($historyChart)) }}" x-data="surveillanceCharts(@js($historyChart))">
                    <figcaption class="text-sm font-semibold">{{ $name }}</figcaption>
                    <div class="relative mt-3 h-72 w-full sm:h-80" wire:ignore><canvas x-ref="trend" role="img" aria-label="{{ $name }} current versus expected cases">Exact values appear in the chart data table below.</canvas></div>
                    <p x-show="loading" role="status" class="text-xs text-muted">Loading chart...</p>
                    <p x-show="failed" x-cloak role="alert" class="mt-3 text-sm text-amber-800">Chart could not be loaded. Exact saved values are available below.</p>
                    <details wire:ignore.self class="text-xs"><summary class="cursor-pointer">View exact chart values</summary><div class="overflow-x-auto"><table class="mt-2 w-full text-left"><thead><tr><th class="p-2">Window end</th><th class="p-2">Current cases</th><th class="p-2">Expected cases</th><th class="p-2">Result</th></tr></thead><tbody>@foreach ($series as $point)<tr><td class="p-2">{{ $point->run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }}</td><td class="p-2">{{ $point->observed }}</td><td class="p-2">{{ $point->expected !== null ? number_format($point->expected, 2) : 'Not enough data' }}</td><td class="p-2"><x-surveillance-outcome :outcome="$point->outcome" :threshold-reached="$point->calculation['threshold_reached'] ?? false" /></td></tr>@endforeach</tbody></table></div></details>
                </figure>
            @empty<p class="text-sm text-muted">No recorded syndrome trends available.</p>@endforelse
        </div>
    </section>
    @endif
    @if ($section === 'alerts')
    <section class="patient-card space-y-4">
        <h2 class="text-lg font-semibold">Internal surveillance alerts</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-blush"><tr><th class="p-3">Reference / syndrome</th><th class="p-3">Current / expected cases</th><th class="p-3">Detected</th><th class="p-3">Review status / assignee</th><th class="p-3">Action</th></tr></thead><tbody class="divide-y divide-line">
            @forelse ($alerts as $alert)<tr wire:key="alert-{{ $alert->id }}"><td class="p-3"><span class="font-medium">{{ $alert->result->syndrome_name }}</span><span class="mt-1 block text-xs font-semibold">{{ $alert->result->outcome === 'Signal' ? 'Below-minimum statistical signal' : (isset($alert->result->calculation['minimum_case_threshold']) ? 'Threshold-triggered statistical alert' : 'Historical statistical alert') }}</span><span class="mt-1 block text-xs text-muted">Minimum at detection: {{ $alert->result->calculation['minimum_case_threshold'] ?? 'Not recorded (legacy result)' }}</span><span class="mt-1 block text-muted">{{ $alert->reference }}</span><span class="mt-1 block text-muted">{{ $alert->scope }}</span></td><td class="p-3">{{ $alert->result->observed }} / {{ number_format($alert->result->expected, 2) }}</td><td class="p-3">{{ $alert->detected_at->setTimezone('Asia/Manila')->format('d M Y H:i') }}</td><td class="p-3"><span @class(['rounded-full px-2 py-1 font-medium whitespace-nowrap', 'bg-red-100 text-red-800' => in_array($alert->status, ['New', 'Escalated']), 'bg-blue-100 text-blue-800' => $alert->status === 'Under Review', 'bg-green-100 text-green-800' => in_array($alert->status, ['Reviewed', 'Resolved']), 'bg-gray-100 text-gray-700' => $alert->status === 'Dismissed'])>{{ $alert->status }}</span><span class="mt-2 block text-muted">{{ $alert->reviewer?->name ?? 'Unassigned' }}</span></td><td class="p-3">@can('surveillance-review')<button type="button" wire:click="openAlert({{ $alert->id }})" class="patient-button">Investigate</button>@else<span class="text-muted">Healthcare review only</span>@endcan</td></tr>@empty<tr><td colspan="5" class="p-5 text-muted">No internal alerts match these filters.</td></tr>@endforelse
        </tbody></table></div>{{ $alerts->links() }}
    </section>
    @endif
    @if ($selected)
        <section id="alert-investigation" tabindex="-1" class="patient-card space-y-5 scroll-mt-4" aria-label="Alert investigation">
            <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-semibold">Investigate {{ $selected->reference }}</h2><button type="button" wire:click="closeAlert" class="patient-button">Close investigation</button></div>
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The system detected an unusual increase in {{ $selected->result->syndrome_name }} cases during this 48-hour monitoring period compared with the historical case pattern for Barangay Alabang. This alert requires review and does not confirm a disease or an outbreak.</p>
            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900"><h3 class="font-semibold">What to review next</h3><p class="mt-2">Check the counted patient encounters, confirm the recorded symptoms and residence, and look for repeat visits or incomplete records. Assign a reviewer and record your findings below. Follow the health center's investigation process if further action is needed.</p></div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs text-muted">Syndrome category</dt><dd class="mt-1 font-semibold">{{ $selected->result->syndrome_name }}</dd></div><div><dt class="text-xs text-muted">Current cases</dt><dd class="mt-1 text-xl font-semibold">{{ $selected->result->observed }}</dd></div>
                <div><dt class="text-xs text-muted">Minimum at detection</dt><dd class="mt-1 text-xl font-semibold">{{ $selected->result->calculation['minimum_case_threshold'] ?? 'Not recorded (legacy result)' }}</dd></div><div><dt class="text-xs text-muted">Statistical result</dt><dd class="mt-1"><x-surveillance-outcome :outcome="$selected->result->outcome" :threshold-reached="$selected->result->calculation['threshold_reached'] ?? false" /></dd></div><div><dt class="text-xs text-muted">Historical expected cases</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($selected->result->expected, 2) }}</dd></div>
                <div><dt class="text-xs text-muted">Surveillance coverage</dt><dd class="mt-1">{{ $selected->scope }}</dd></div>
                <div><dt class="text-xs text-muted">Monitoring period</dt><dd>{{ $selected->result->run->window_start->setTimezone('Asia/Manila')->format('d M Y H:i') }} – {{ $selected->result->run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }}</dd></div>
                <div><dt class="text-xs text-muted">Detection time / status</dt><dd>{{ $selected->detected_at->setTimezone('Asia/Manila')->format('d M Y H:i') }} · {{ $selected->status }}</dd></div>
            </dl>
            <details wire:ignore.self class="rounded-lg border border-line p-4 text-sm"><summary class="cursor-pointer font-semibold">Statistical Details</summary>
                <p class="mt-4">{{ $selected->result->reason }}</p>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-muted">Statistical method / rule version</dt><dd>{{ $selected->result->model }} / #{{ $selected->result->syndrome_rule_version_id }}</dd></div>
                    <div><dt class="text-xs text-muted">Statistical result (raw / adjusted probability)</dt><dd>{{ sprintf('%.6g', $selected->result->probability) }} / {{ sprintf('%.6g', $selected->result->adjusted_probability) }}</dd></div>
                    <div><dt class="text-xs text-muted">Historical periods / residual dispersion</dt><dd>{{ count($selected->result->calculation['baseline_periods']) }} / {{ number_format($selected->result->calculation['dispersion'], 2) }}</dd></div>
                    <div><dt class="text-xs text-muted">Saved statistical alert / watch probabilities</dt><dd>{{ $selected->result->run->settings['alpha'] }} / {{ $selected->result->run->settings['watch_alpha'] }}</dd></div>
                </dl>
                <p class="my-3 text-xs">{{ $selected->result->calculation['seasonality'] }}. Attendance-adjusted rate. {{ $selected->result->calculation['multiplicity'] }} across {{ $selected->result->calculation['family_size'] }} categories. Current structured coverage: {{ number_format(100 * $selected->result->calculation['current_coverage'], 1) }}%.</p><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead><tr><th class="p-2">Comparable period start</th><th class="p-2">Cases</th><th class="p-2">Eligible encounters</th><th class="p-2">Coverage</th></tr></thead><tbody>@foreach ($selected->result->calculation['baseline_periods'] as $period)<tr><td class="p-2">{{ \Carbon\CarbonImmutable::parse($period['start'])->setTimezone('Asia/Manila')->format('d M Y H:i') }}</td><td class="p-2">{{ $period['count'] }}</td><td class="p-2">{{ $period['attendance'] }}</td><td class="p-2">{{ number_format($period['coverage'] * 100, 1) }}%</td></tr>@endforeach</tbody></table></div>
            </details>
            <div><h3 class="font-semibold">Counted illness episodes</h3><div class="mt-3 overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-blush"><tr><th class="p-3">Patient / encounter</th><th class="p-3">Structured evidence</th><th class="p-3">Doctor's recorded diagnosis</th><th class="p-3">Medical record</th></tr></thead><tbody>@foreach ($selected->result->cases as $case)<tr><td class="p-3">{{ $case->record->patient->first_name }} {{ $case->record->patient->last_name }}<span class="block text-muted">#{{ $case->record->patient_id }} · {{ $case->occurred_at->setTimezone('Asia/Manila')->format('d M Y H:i') }}</span></td><td class="p-3">{{ implode(', ', $case->evidence['structured_symptoms']) }}<p class="mt-2 whitespace-pre-line break-words"><span class="font-medium">Chief complaint:</span> {{ $case->evidence['chief_complaint'] ?? $case->record->chief_complaint ?: 'Not recorded' }}</p><p class="mt-2 whitespace-pre-line break-words"><span class="font-medium">Assessment:</span> {{ $case->evidence['assessment_notes'] ?? $case->record->assessment_notes ?: 'Not recorded' }}</p></td><td class="p-3">{{ $case->evidence['diagnosis'] ?? $case->record->diagnosis }}</td><td class="p-3"><a href="{{ route('medical-records.show', ['record' => $case->record->patient_id]) }}" wire:navigate class="underline">View patient medical history</a></td></tr>@endforeach</tbody></table></div></div>
            <form wire:submit="saveReview" class="space-y-4 border-t border-line pt-5">
                <h3 class="font-semibold">Record investigation action</h3>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="review-status" class="patient-label">Next review status</label><select id="review-status" wire:model="reviewStatus" class="patient-input w-full">@foreach ($reviewStatuses as $status)<option>{{ $status }}</option>@endforeach</select></div><div><label for="reviewer-id" class="patient-label">Assigned reviewer</label><select id="reviewer-id" wire:model="reviewerId" class="patient-input w-full"><option value="">Unassigned</option>@foreach ($reviewers as $reviewer)<option value="{{ $reviewer->user_id }}">{{ $reviewer->name }} · {{ $reviewer->role }}</option>@endforeach</select></div></div>
                <div><label for="investigation-notes" class="patient-label">Investigation notes</label><textarea id="investigation-notes" wire:model="notes" required minlength="5" maxlength="10000" rows="3" class="patient-input w-full"></textarea></div><div><label for="review-outcome" class="patient-label">Assessment / outcome (required when closing or escalating)</label><textarea id="review-outcome" wire:model="outcome" maxlength="5000" rows="2" class="patient-input w-full"></textarea></div>
                <button type="submit" wire:confirm="Save this review action and investigation notes to the permanent alert history?" wire:loading.attr="disabled" class="patient-button bg-brand-blue">Save investigation action</button>
            </form>
            <div><h3 class="font-semibold">Alert action history</h3><p class="mt-2 text-xs text-muted">{{ $selected->created_at->setTimezone('Asia/Manila')->format('d M Y H:i') }} · Internal alert generated automatically · New</p>@foreach ($selected->reviews->sortByDesc('id') as $review)<article class="mt-3 rounded-lg bg-blush p-3 text-xs"><p class="font-semibold">{{ $review->from_status }} ? {{ $review->to_status }} · {{ $review->user->name }} · {{ $review->created_at->setTimezone('Asia/Manila')->format('d M Y H:i') }}</p><p class="mt-2 whitespace-pre-line">{{ $review->notes }}</p>@if ($review->outcome)<p class="mt-2 whitespace-pre-line">Assessment: {{ $review->outcome }}</p>@endif</article>@endforeach</div>
        </section>
    @endif
    @if ($section !== 'overview' && $legacyAlerts->isNotEmpty())
        <section class="patient-card"><h2 class="text-lg font-semibold">Preserved legacy surveillance alerts</h2><p class="mt-2 text-xs text-muted">Historical records from the previous surveillance prototype. These alerts are read only and are excluded from this engine's statistical results.</p><div class="mt-4 space-y-3">@foreach ($legacyAlerts as $legacy)<details wire:key="legacy-alert-{{ $legacy->id }}" wire:ignore.self class="rounded-lg border border-line p-3 text-xs"><summary class="cursor-pointer">{{ $legacy->reference }} · {{ $legacy->syndrome }} · {{ $legacy->status }} · {{ $legacy->mode }}</summary><p class="mt-2">Peak count: {{ $legacy->peak_count }} · First detected: {{ $legacy->first_detected_at?->setTimezone('Asia/Manila')->format('d M Y H:i') }}</p><p class="mt-2 whitespace-pre-line">{{ $legacy->review_notes ?? 'No legacy review notes recorded.' }}</p></details>@endforeach</div></section>
    @endif
    @if ($section === 'history')
        @can('surveillance-manage')<section class="patient-card"><h2 class="text-lg font-semibold">Recent detection checks</h2><p class="mt-2 text-sm text-muted">The latest 20 checks within the selected dates, including failed or skipped checks. Saved results retain the settings used at that time.</p><div class="mt-4 space-y-3">@forelse ($runs as $run)<details wire:key="surveillance-run-{{ $run->id }}" wire:ignore.self class="rounded-lg border border-line p-4"><summary class="cursor-pointer text-sm font-medium">#{{ $run->id }} · {{ ucfirst($run->source) }} · {{ $run->status }} · {{ $run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }}</summary><p class="mt-3 text-xs">{{ $run->window_start->setTimezone('Asia/Manila')->format('d M Y H:i') }} to {{ $run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }} · {{ $run->eligible_cases }} eligible patients</p>@if ($run->error)<p class="mt-2 text-sm text-red-700">{{ $run->error }}</p>@endif<div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Syndrome category</th><th class="p-2">Current cases</th><th class="p-2">Expected cases</th><th class="p-2">Result</th></tr></thead><tbody>@foreach ($run->results as $result)<tr><td class="p-2">{{ $result->syndrome_name }}</td><td class="p-2">{{ $result->observed }}</td><td class="p-2">{{ $result->expected !== null ? number_format($result->expected, 2) : 'Not enough data' }}</td><td class="p-2"><x-surveillance-outcome :outcome="$result->outcome" :threshold-reached="$result->calculation['threshold_reached'] ?? false" /></td></tr>@endforeach</tbody></table></div></details>@empty<p class="text-sm text-muted">No detection runs recorded in these dates.</p>@endforelse</div></section>@endcan
    @endif
</div>
