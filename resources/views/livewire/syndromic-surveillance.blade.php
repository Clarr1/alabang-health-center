<div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-7 sm:px-7 lg:px-8" wire:poll.60s x-on:surveillance-alert-opened.window="$nextTick(() => document.getElementById('alert-investigation')?.focus())">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><p class="text-xs font-semibold uppercase tracking-wide text-muted">Syndromic Surveillance</p><h1 class="mt-2 text-2xl font-semibold">{{ match ($section) { 'alerts' => 'Surveillance Alerts', 'history' => 'Surveillance History', default => 'Overview' } }}</h1><p class="mt-2 text-sm text-muted">Barangay Alabang, Muntinlupa City · All displayed times Asia/Manila</p></div>
        <div class="flex flex-wrap gap-2">
            @can('surveillance-manage')<button type="button" wire:click="detect" wire:confirm="Check eligible patient records from the last 48 hours? Results and internal alerts will be saved. For a simulated preview, use Detection Settings." wire:loading.attr="disabled" class="patient-button bg-brand-blue">Check for case increases</button>@endcan
        </div>
    </div>
    <x-surveillance-nav :active="match ($section) { 'alerts' => 'surveillance.alerts', 'history' => 'surveillance.history', default => 'surveillance.index' }" />
    <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Development surveillance · Signals identify unusual syndrome patterns requiring healthcare personnel review. They do not confirm a disease or an outbreak. Alerts remain inside this application.</p>
    @if (session('surveillance-status'))<p role="status" class="patient-card text-sm">{{ session('surveillance-status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p wire:loading.delay role="status" class="text-xs text-muted">Updating surveillance information…</p>
    @if ($section === 'overview')
        <section aria-label="Surveillance summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="patient-card"><h2 class="text-sm text-muted">Eligible patients · 48 hours</h2><p class="mt-3 text-3xl font-semibold">{{ $latest?->eligible_cases ?? '—' }}</p><p class="mt-2 text-xs text-muted">Unique patients in the latest completed check.</p></article>
            <article class="patient-card"><h2 class="text-sm text-muted">Monitored syndrome categories</h2><p class="mt-3 text-3xl font-semibold">{{ $latest?->results->count() ?? '—' }}</p><p class="mt-2 text-xs text-muted">{{ $latest?->results->whereIn('outcome', ['Watch', 'Alert'])->count() ?? 0 }} categories flagged for an unusual increase.</p></article>
            <a href="{{ route('surveillance.alerts') }}" wire:navigate class="patient-card block hover:border-blue-300"><h2 class="text-sm text-muted">Active alerts</h2><p class="mt-3 text-3xl font-semibold">{{ $activeAlerts }}</p><p class="mt-2 text-xs text-muted">New, under review, or escalated.</p></a>
            <a href="{{ route('surveillance.alerts') }}" wire:navigate class="patient-card block border-amber-200 bg-amber-50"><h2 class="text-sm text-amber-900">Alerts requiring review</h2><p class="mt-3 text-3xl font-semibold text-amber-900">{{ $awaitingReview }}</p><p class="mt-2 text-xs text-amber-900">New or escalated alerts. Open to review.</p></a>
        </section>
    @endif
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
    <section class="patient-card space-y-4">
        <h2 class="text-lg font-semibold">Latest completed detection</h2>
        @if ($latest)
            <p class="text-sm">{{ $latest->window_start->setTimezone('Asia/Manila')->format('d M Y H:i') }} to {{ $latest->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }} (end excluded)</p>
            <p class="text-xs text-muted">{{ $latest->eligible_cases }} unique eligible patients with valid encounters in this window, across all syndromes. Syndrome totals count illness episodes and may overlap. Run #{{ $latest->id }} · {{ ucfirst($latest->source) }} · Completed {{ $latest->finished_at?->setTimezone('Asia/Manila')->format('d M Y H:i') }}</p>
            <p class="text-xs text-muted">Cases below count separate illness episodes. Blue = current cases; gray = expected cases; red or amber = unusual increases. One patient may appear in more than one syndrome category.</p>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-blush"><tr><th class="p-3">Syndrome category</th><th class="p-3">Current cases</th><th class="p-3">Expected cases</th><th class="p-3">Result</th><th class="p-3">What to do next</th></tr></thead><tbody class="divide-y divide-line">
                @forelse ($results as $result)
                    @php
                        $scale = max(1, $results->max('observed'), $results->max('expected') ?? 0);
                    @endphp
                    <tr><td class="min-w-[180px] p-3 font-medium">{{ $result->syndrome_name }}<div class="mt-2 space-y-1" aria-hidden="true"><div @class(['h-2 rounded', 'bg-red-500' => $result->outcome === 'Alert', 'bg-amber-500' => $result->outcome === 'Watch', 'bg-blue-500' => ! in_array($result->outcome, ['Alert', 'Watch'])]) style="width: {{ 100 * $result->observed / $scale }}%"></div>@if ($result->expected !== null)<div class="h-2 rounded bg-gray-400" style="width: {{ 100 * $result->expected / $scale }}%"></div>@endif</div></td><td class="p-3 font-semibold">{{ $result->observed }}</td><td class="p-3">{{ $result->expected !== null ? number_format($result->expected, 2) : 'Not enough data' }}</td><td class="p-3"><x-surveillance-outcome :outcome="$result->outcome" /></td><td class="max-w-md p-3 leading-relaxed">{{ match ($result->outcome) { 'Alert' => 'Review the surveillance alert and the related patient records.', 'Watch' => 'Monitor recent cases and check that symptom records are complete.', 'Normal' => 'Continue routine monitoring.', default => 'Check approved syndrome rules, verified residence, and the completeness of current and historical symptom records.' } }}</td></tr>
                @empty<tr><td colspan="5" class="p-5 text-muted">No active syndrome categories in this detection.</td></tr>@endforelse
            </tbody></table></div>
        @else<p class="text-sm text-muted">No completed checks yet. Approve syndrome categories, save a reviewed detection sensitivity, and verify patient residence before checking for case increases.</p>@endif
        <div class="flex flex-wrap gap-3"><a href="{{ route('surveillance.alerts') }}" wire:navigate class="patient-button">View surveillance alerts</a><a href="{{ route('surveillance.history') }}" wire:navigate class="patient-button">View surveillance history</a></div>
    </section>
    @endif
    @if ($section === 'history')
    <section class="patient-card space-y-4">
        <h2 class="text-lg font-semibold">Current and expected case trends</h2><p class="text-sm text-muted">Blue: current cases · Dashed gray: expected cases · Red: needs review · Amber: watch. Each point is a saved detection result. Rolling windows overlap; counts should not be added together.</p>
        <div class="grid gap-5 lg:grid-cols-2">
            @forelse ($trends as $name => $points)
                @php
                    $series = $points->values();
                    $maximum = max(1, $series->max('observed'), $series->max('expected') ?? 0);
                    $observedPoints = $series->map(fn ($point, $index) => (20 + $index * 460 / max(1, $series->count() - 1)).','. (150 - 130 * $point->observed / $maximum))->join(' ');
                @endphp
                <figure class="min-w-0 rounded-lg border border-line p-4"><figcaption class="text-sm font-semibold">{{ $name }}</figcaption>
                    <svg viewBox="0 0 500 180" role="img" aria-label="{{ $name }} current versus expected cases" class="mt-3 w-full"><path d="M20 20v130h460" fill="none" stroke="#d1d5db"/><text x="18" y="15" font-size="10">{{ ceil($maximum) }} cases</text><text x="8" y="153" font-size="10">0</text><polyline points="{{ $observedPoints }}" fill="none" stroke="#2563eb" stroke-width="2"/>
                        @foreach ($series as $index => $point)
                            @php
                                $x = 20 + $index * 460 / max(1, $series->count() - 1);
                            @endphp
                            <circle cx="{{ $x }}" cy="{{ 150 - 130 * $point->observed / $maximum }}" r="4" fill="{{ match ($point->outcome) { 'Alert' => '#dc2626', 'Watch' => '#d97706', default => '#2563eb' } }}"><title>{{ $point->run->window_end->setTimezone('Asia/Manila')->format('d M H:i') }}: {{ $point->observed }} current cases; {{ $point->outcome }}</title></circle>
                            @if ($point->expected !== null)
                                <circle cx="{{ $x }}" cy="{{ 150 - 130 * $point->expected / $maximum }}" r="3" fill="#6b7280"><title>{{ $point->run->window_end->setTimezone('Asia/Manila')->format('d M H:i') }}: {{ number_format($point->expected, 2) }} expected cases</title></circle>
                                @if ($index > 0 && $series[$index - 1]->expected !== null)<line x1="{{ 20 + ($index - 1) * 460 / max(1, $series->count() - 1) }}" y1="{{ 150 - 130 * $series[$index - 1]->expected / $maximum }}" x2="{{ $x }}" y2="{{ 150 - 130 * $point->expected / $maximum }}" stroke="#6b7280" stroke-width="2" stroke-dasharray="5 4"/>@endif
                            @endif
                        @endforeach
                        <text x="20" y="170" font-size="10">{{ $series->first()->run->window_end->setTimezone('Asia/Manila')->format('d M H:i') }}</text><text x="480" y="170" text-anchor="end" font-size="10">{{ $series->last()->run->window_end->setTimezone('Asia/Manila')->format('d M H:i') }}</text>
                    </svg>
                    <details class="text-xs"><summary class="cursor-pointer">View exact chart values</summary><div class="overflow-x-auto"><table class="mt-2 w-full text-left"><thead><tr><th class="p-2">Window end</th><th class="p-2">Current cases</th><th class="p-2">Expected cases</th><th class="p-2">Result</th></tr></thead><tbody>@foreach ($series as $point)<tr><td class="p-2">{{ $point->run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }}</td><td class="p-2">{{ $point->observed }}</td><td class="p-2">{{ $point->expected !== null ? number_format($point->expected, 2) : 'Not enough data' }}</td><td class="p-2"><x-surveillance-outcome :outcome="$point->outcome" /></td></tr>@endforeach</tbody></table></div></details>
                </figure>
            @empty<p class="text-sm text-muted">No recorded syndrome trends available.</p>@endforelse
        </div>
    </section>
    @endif
    @if ($section === 'alerts')
    <section class="patient-card space-y-4">
        <h2 class="text-lg font-semibold">Internal surveillance alerts</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-blush"><tr><th class="p-3">Reference / syndrome</th><th class="p-3">Current / expected cases</th><th class="p-3">Detected</th><th class="p-3">Review status / assignee</th><th class="p-3">Action</th></tr></thead><tbody class="divide-y divide-line">
            @forelse ($alerts as $alert)<tr wire:key="alert-{{ $alert->id }}"><td class="p-3"><span class="font-medium">{{ $alert->result->syndrome_name }}</span><span class="mt-1 block text-muted">{{ $alert->reference }}</span><span class="mt-1 block text-muted">{{ $alert->scope }}</span></td><td class="p-3">{{ $alert->result->observed }} / {{ number_format($alert->result->expected, 2) }}</td><td class="p-3">{{ $alert->detected_at->setTimezone('Asia/Manila')->format('d M Y H:i') }}</td><td class="p-3"><span @class(['rounded-full px-2 py-1 font-medium whitespace-nowrap', 'bg-red-100 text-red-800' => in_array($alert->status, ['New', 'Escalated']), 'bg-blue-100 text-blue-800' => $alert->status === 'Under Review', 'bg-green-100 text-green-800' => in_array($alert->status, ['Reviewed', 'Resolved']), 'bg-gray-100 text-gray-700' => $alert->status === 'Dismissed'])>{{ $alert->status }}</span><span class="mt-2 block text-muted">{{ $alert->reviewer?->name ?? 'Unassigned' }}</span></td><td class="p-3">@can('surveillance-review')<button type="button" wire:click="openAlert({{ $alert->id }})" class="patient-button">Investigate</button>@else<span class="text-muted">Healthcare review only</span>@endcan</td></tr>@empty<tr><td colspan="5" class="p-5 text-muted">No internal alerts match these filters.</td></tr>@endforelse
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
                <div><dt class="text-xs text-muted">Expected cases</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($selected->result->expected, 2) }}</dd></div>
                <div><dt class="text-xs text-muted">Surveillance coverage</dt><dd class="mt-1">{{ $selected->scope }}</dd></div>
                <div><dt class="text-xs text-muted">Monitoring period</dt><dd>{{ $selected->result->run->window_start->setTimezone('Asia/Manila')->format('d M Y H:i') }} – {{ $selected->result->run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }}</dd></div>
                <div><dt class="text-xs text-muted">Detection time / status</dt><dd>{{ $selected->detected_at->setTimezone('Asia/Manila')->format('d M Y H:i') }} · {{ $selected->status }}</dd></div>
            </dl>
            <details class="rounded-lg border border-line p-4 text-sm"><summary class="cursor-pointer font-semibold">Statistical Details</summary>
                <p class="mt-4">{{ $selected->result->reason }}</p>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-muted">Statistical method / rule version</dt><dd>{{ $selected->result->model }} / #{{ $selected->result->syndrome_rule_version_id }}</dd></div>
                    <div><dt class="text-xs text-muted">Statistical result (raw / adjusted probability)</dt><dd>{{ sprintf('%.6g', $selected->result->probability) }} / {{ sprintf('%.6g', $selected->result->adjusted_probability) }}</dd></div>
                    <div><dt class="text-xs text-muted">Historical periods / residual dispersion</dt><dd>{{ count($selected->result->calculation['baseline_periods']) }} / {{ number_format($selected->result->calculation['dispersion'], 2) }}</dd></div>
                    <div><dt class="text-xs text-muted">Saved alert / watch thresholds</dt><dd>{{ $selected->result->run->settings['alpha'] }} / {{ $selected->result->run->settings['watch_alpha'] }}</dd></div>
                </dl>
                <p class="my-3 text-xs">{{ $selected->result->calculation['seasonality'] }}. Attendance-adjusted rate. {{ $selected->result->calculation['multiplicity'] }} across {{ $selected->result->calculation['family_size'] }} categories. Current structured coverage: {{ number_format(100 * $selected->result->calculation['current_coverage'], 1) }}%.</p><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead><tr><th class="p-2">Comparable period start</th><th class="p-2">Cases</th><th class="p-2">Eligible encounters</th><th class="p-2">Coverage</th></tr></thead><tbody>@foreach ($selected->result->calculation['baseline_periods'] as $period)<tr><td class="p-2">{{ \Carbon\CarbonImmutable::parse($period['start'])->setTimezone('Asia/Manila')->format('d M Y H:i') }}</td><td class="p-2">{{ $period['count'] }}</td><td class="p-2">{{ $period['attendance'] }}</td><td class="p-2">{{ number_format($period['coverage'] * 100, 1) }}%</td></tr>@endforeach</tbody></table></div>
            </details>
            <div><h3 class="font-semibold">Counted illness episodes</h3><div class="mt-3 overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-blush"><tr><th class="p-3">Patient / encounter</th><th class="p-3">Structured evidence</th><th class="p-3">Doctor's recorded diagnosis</th><th class="p-3">Medical record</th></tr></thead><tbody>@foreach ($selected->result->cases as $case)<tr><td class="p-3">{{ $case->record->patient->first_name }} {{ $case->record->patient->last_name }}<span class="block text-muted">#{{ $case->record->patient_id }} · {{ $case->occurred_at->setTimezone('Asia/Manila')->format('d M Y H:i') }}</span></td><td class="p-3">{{ implode(', ', $case->evidence['structured_symptoms']) }}</td><td class="p-3">{{ $case->record->diagnosis }}</td><td class="p-3"><a href="{{ route('medical-records.show', ['record' => $case->record->patient_id]) }}" wire:navigate class="underline">View patient medical history</a></td></tr>@endforeach</tbody></table></div></div>
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
        <section class="patient-card"><h2 class="text-lg font-semibold">Preserved legacy surveillance alerts</h2><p class="mt-2 text-xs text-muted">Historical records from the previous surveillance prototype. These alerts are read only and are excluded from this engine's statistical results.</p><div class="mt-4 space-y-3">@foreach ($legacyAlerts as $legacy)<details class="rounded-lg border border-line p-3 text-xs"><summary class="cursor-pointer">{{ $legacy->reference }} · {{ $legacy->syndrome }} · {{ $legacy->status }} · {{ $legacy->mode }}</summary><p class="mt-2">Peak count: {{ $legacy->peak_count }} · First detected: {{ $legacy->first_detected_at?->setTimezone('Asia/Manila')->format('d M Y H:i') }}</p><p class="mt-2 whitespace-pre-line">{{ $legacy->review_notes ?? 'No legacy review notes recorded.' }}</p></details>@endforeach</div></section>
    @endif
    @if ($section === 'history')
        @can('surveillance-manage')<section class="patient-card"><h2 class="text-lg font-semibold">Recent detection checks</h2><p class="mt-2 text-sm text-muted">The latest 20 checks within the selected dates, including failed or skipped checks. Saved results retain the settings used at that time.</p><div class="mt-4 space-y-3">@forelse ($runs as $run)<details class="rounded-lg border border-line p-4"><summary class="cursor-pointer text-sm font-medium">#{{ $run->id }} · {{ ucfirst($run->source) }} · {{ $run->status }} · {{ $run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }}</summary><p class="mt-3 text-xs">{{ $run->window_start->setTimezone('Asia/Manila')->format('d M Y H:i') }} to {{ $run->window_end->setTimezone('Asia/Manila')->format('d M Y H:i') }} · {{ $run->eligible_cases }} eligible patients</p>@if ($run->error)<p class="mt-2 text-sm text-red-700">{{ $run->error }}</p>@endif<div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Syndrome category</th><th class="p-2">Current cases</th><th class="p-2">Expected cases</th><th class="p-2">Result</th></tr></thead><tbody>@foreach ($run->results as $result)<tr><td class="p-2">{{ $result->syndrome_name }}</td><td class="p-2">{{ $result->observed }}</td><td class="p-2">{{ $result->expected !== null ? number_format($result->expected, 2) : 'Not enough data' }}</td><td class="p-2"><x-surveillance-outcome :outcome="$result->outcome" /></td></tr>@endforeach</tbody></table></div></details>@empty<p class="text-sm text-muted">No detection runs recorded in these dates.</p>@endforelse</div></section>@endcan
    @endif
</div>
