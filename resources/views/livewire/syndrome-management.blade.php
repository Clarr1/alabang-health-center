<div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-7 sm:px-7 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-semibold">Syndrome definitions &amp; settings</h1><p class="mt-2 text-xs text-muted">Barangay Alabang, Muntinlupa City · Development and clinical review</p></div>
        <a href="{{ route('surveillance.index') }}" wire:navigate class="patient-button bg-white">Surveillance dashboard</a>
    </div>
    <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Definitions are configurable clinical rules. A doctor or authorized administrator must approve each version. Category names alone have no symptom mappings and cannot produce alerts. No signal confirms a diagnosis or outbreak.</p>
    @if (session('surveillance-status'))<p role="status" class="patient-card text-sm">{{ session('surveillance-status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p wire:loading.delay role="status" class="text-xs text-muted">Saving or loading configuration…</p>
    <div class="grid items-start gap-6 xl:grid-cols-2">
        <section class="patient-card space-y-4">
            <h2 class="text-lg font-semibold">Syndrome categories</h2>
            @forelse ($categories as $category)
                <article wire:key="category-{{ $category->id }}" class="rounded-lg border border-line p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold">{{ $category->name }}</h3><span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></div>
                    <p class="mt-2 text-sm text-muted">{{ $category->description }}</p>
                    @can('surveillance-manage')
                        <div class="mt-3 flex gap-2"><button type="button" wire:click="edit({{ $category->id }})" class="patient-button text-xs">Edit / new version</button><button type="button" wire:click="toggle({{ $category->id }})" wire:confirm="Change whether this syndrome is monitored? Saved classifications and alerts will be preserved." class="patient-button text-xs">{{ $category->is_active ? 'Deactivate' : 'Activate' }}</button></div>
                    @endcan
                    <details class="mt-3 text-xs" open><summary class="cursor-pointer font-medium">Definition versions and approval history</summary>
                        @forelse ($category->rules as $rule)
                            <div class="mt-3 rounded-lg bg-blush p-3">
                                <p class="font-semibold">Version {{ $rule->version }} · {{ $rule->approved_at ? 'Approved' : 'Unreviewed' }}</p>
                                <p class="mt-1">Match at least {{ $rule->minimum_matches }} of: {{ $rule->symptoms->map(fn ($symptom) => $symptom->pivot->symptom_name)->join(', ') }}</p>
                                <p class="mt-1">Applies from {{ $rule->applies_from->setTimezone('Asia/Manila')->format('d M Y') }} to previously unclassified encounters.</p>
                                <p class="mt-1 whitespace-pre-line">{{ $rule->rationale }}</p>
                                @if ($rule->approved_at)
                                    <p class="mt-2 text-muted">Approved by account #{{ $rule->approved_by }} · {{ $rule->approved_at->setTimezone('Asia/Manila')->format('d M Y H:i') }}</p>
                                @else
                                    <button type="button" wire:click="approve({{ $rule->id }})" wire:confirm="I am authorized to clinically review this definition and approve these symptom mappings, minimum matches, and historical application date for development surveillance. Continue?" wire:loading.attr="disabled" class="patient-button mt-3 bg-brand-blue">Approve clinical definition</button>
                                @endif
                            </div>
                        @empty<p class="mt-2 text-muted">No rule defined. Not eligible for detection.</p>@endforelse
                    </details>
                </article>
            @empty<p class="text-sm text-muted">No syndrome categories. Create a definition using the existing structured symptom catalog.</p>@endforelse
        </section>
        @can('surveillance-manage')
            <form wire:submit="saveDefinition" class="patient-card space-y-4">
                <h2 class="text-lg font-semibold">{{ $categoryId ? 'Draft a new definition version' : 'Create syndrome category' }}</h2>
                <p class="text-xs text-muted">Changes create an unreviewed version. Existing approved rules remain in effect until a new version is approved. Existing classifications are never rewritten.</p>
                <div><label for="syndrome-name" class="patient-label">Category name</label><input id="syndrome-name" wire:model="name" required maxlength="255" class="patient-input w-full"></div>
                <div><label for="syndrome-description" class="patient-label">Description</label><textarea id="syndrome-description" wire:model="description" maxlength="5000" class="patient-input w-full"></textarea></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="isActive" class="rounded"> Monitor this category</label>
                <fieldset><legend class="patient-label">Structured symptom mappings</legend><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach ($symptoms as $symptom)<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="symptomIds" value="{{ $symptom->id }}" class="rounded">{{ $symptom->name }}</label>@endforeach</div></fieldset>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="minimum-matches" class="patient-label">Minimum distinct symptoms required</label><input id="minimum-matches" type="number" min="1" max="50" wire:model="minimumMatches" required class="patient-input w-full"></div><div><label for="applies-from" class="patient-label">Applies from (Manila date)</label><input id="applies-from" type="date" wire:model="appliesFrom" max="{{ now('Asia/Manila')->toDateString() }}" required class="patient-input w-full"></div></div>
                <p class="text-xs text-muted">A past date permits classification of reliable, previously unclassified historical records after approval. A changed rule needs sufficient history under that version before statistical alerts resume.</p>
                <div><label for="rule-rationale" class="patient-label">Clinical rationale / review requirements</label><textarea id="rule-rationale" wire:model="rationale" required minlength="10" maxlength="5000" rows="3" class="patient-input w-full"></textarea></div>
                <div class="flex gap-3"><button type="submit" wire:loading.attr="disabled" class="patient-button bg-brand-blue">Save unreviewed definition</button><button type="button" wire:click="newDefinition" class="patient-button">Clear / new category</button></div>
            </form>
        @endcan
    </div>
    @can('surveillance-manage')
        <form wire:submit="saveSettings" class="patient-card space-y-5">
            <h2 class="text-lg font-semibold">Statistical settings for development testing</h2>
            <p class="text-sm text-muted">Defaults are engineering starting points requiring local validation. Counts are illness episodes separated by the configured symptom-free encounter gap. Baselines use matching weekdays, attendance adjustment, and the same approved rule version. Poisson assumes independent counts with equal mean and variance; excess residual dispersion selects a negative-binomial model. Bonferroni correction applies across monitored categories within each run. Repeated rolling evaluations are correlated; the threshold does not control false positives across all future runs.</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'window_hours' => ['Monitoring window (hours)', '1'], 'baseline_weeks' => ['Baseline lookback (weeks)', '1'],
                    'minimum_periods' => ['Minimum comparable historical periods', '1'], 'minimum_attendance' => ['Minimum encounters per period', '1'],
                    'minimum_coverage' => ['Minimum structured symptom coverage (0.5–1)', '0.01'], 'episode_gap_hours' => ['Episode separation (hours)', '1'],
                    'alpha' => ['Alert significance after correction', '0.000001'], 'watch_alpha' => ['Watch significance after correction', '0.001'],
                    'overdispersion_ratio' => ['Poisson residual dispersion tolerance', '0.1'],
                ] as $key => [$label, $step])
                    <div><label for="setting-{{ $key }}" class="patient-label">{{ $label }}</label><input id="setting-{{ $key }}" type="number" step="{{ $step }}" wire:model="settings.{{ $key }}" required class="patient-input w-full"></div>
                @endforeach
                <div><label for="frequency-minutes" class="patient-label">Automatic detection interval</label><select id="frequency-minutes" wire:model="settings.frequency_minutes" class="patient-input w-full">@foreach ([15, 30, 60, 120, 360, 720, 1440] as $minutes)<option value="{{ $minutes }}">{{ $minutes }} minutes</option>@endforeach</select></div>
            </div>
            <p class="text-xs text-muted">Annual seasonality is considered only with a lookback of at least 60 weeks, using same-weekday windows within 45 calendar days of the current season. Shorter lookbacks use recent history. Missing attendance, poor symptom coverage, unapproved settings, and all-zero baselines produce Insufficient Data.</p>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="settings.settings_reviewed" required class="mt-1 rounded"><span>I reviewed these statistical assumptions and thresholds for development testing. This does not certify clinical or operational validity.</span></label>
            <button type="submit" wire:confirm="Save this reviewed configuration for subsequent detection runs? Previous results will remain unchanged." wire:loading.attr="disabled" class="patient-button bg-brand-blue">Save reviewed settings</button>
        </form>
        <section class="patient-card"><h2 class="text-lg font-semibold">Administrative audit trail</h2><div class="mt-3 space-y-2">@forelse ($audits as $audit)<p class="text-xs">{{ $audit->created_at->setTimezone('Asia/Manila')->format('d M Y H:i') }} · {{ $audit->action }} · Account #{{ $audit->actor_id }}</p>@empty<p class="text-sm text-muted">No configuration changes recorded.</p>@endforelse</div></section>
    @endcan
</div>
