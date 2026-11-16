<div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-7 sm:px-7 lg:px-8">
    <header>
        <p class="text-xs font-semibold uppercase tracking-wide text-muted">Syndromic Surveillance</p>
        <h1 class="mt-2 text-2xl font-semibold">Detection Settings</h1>
        <p class="mt-2 text-sm text-muted">Choose how the system identifies unusual increases in Barangay Alabang.</p>
    </header>
    <x-surveillance-nav active="surveillance.settings" />
    @if (session('surveillance-status'))<p role="status" class="patient-card text-sm">{{ session('surveillance-status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="grid items-start gap-6 xl:grid-cols-3">
        <form wire:submit="save" class="patient-card space-y-5 xl:col-span-2">
            <div><h2 class="text-lg font-semibold">Detection configuration</h2><p class="mt-2 text-sm text-muted">Higher sensitivity can flag smaller increases and may produce more alerts that need investigation.</p></div>
            @if ($sensitivity === 'custom')<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Your existing custom thresholds are still in use. Select a sensitivity to replace only those thresholds.</p>@endif
            <fieldset class="space-y-3">
                <legend class="patient-label">Detection sensitivity</legend>
                @foreach ($profiles as $key => $profile)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-4 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" wire:model="sensitivity" value="{{ $key }}" class="mt-1 border-line text-blue-600">
                        <span><span class="block text-sm font-semibold">{{ $profile['label'] }}</span><span class="mt-1 block text-sm text-muted">{{ match ($key) { 'low' => 'Flags only stronger, more unusual increases.', 'high' => 'Flags smaller unusual increases; more alerts may need review.', default => 'Balances early warning with the number of alerts to review.' } }}</span></span>
                    </label>
                @endforeach
            </fieldset>
            <dl class="grid gap-4 rounded-lg bg-blush p-4 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold">Monitoring window</dt><dd class="mt-1">48 hours · Fixed</dd><dd class="mt-1 text-xs text-muted">Each detection reviews the previous 48 hours.</dd></div>
                <div><dt class="font-semibold">Historical comparison period</dt><dd class="mt-1">{{ $settings['baseline_weeks'] }} weeks · Managed by the system</dd><dd class="mt-1 text-xs text-muted">Uses comparable weekdays with enough reliable records.</dd></div>
            </dl>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="reviewed" class="mt-1 rounded" required><span>I have reviewed this sensitivity for development testing. Local clinical validation is still required.</span></label>
            @error('reviewed')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            <button type="submit" wire:loading.attr="disabled" class="patient-button bg-brand-blue">Save detection settings</button>
            <p wire:loading wire:target="save" role="status" class="text-sm text-muted">Saving settings…</p>
        </form>
        <section class="patient-card space-y-4">
            <h2 class="text-lg font-semibold">Automatic statistical processing</h2>
            <dl class="space-y-4 text-sm">
                @foreach (['Historical baseline' => 'Automatically calculated', 'Statistical method' => 'Automatically selected', 'Statistical evaluation' => 'Automatically performed', 'Data reliability checks' => 'Required before an alert', 'Multiple syndrome adjustment' => 'Applied automatically', 'Duplicate alert prevention' => 'Enabled', 'Surveillance coverage' => 'Barangay Alabang only'] as $label => $value)
                    <div><dt class="text-muted">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <p class="border-t border-line pt-4 text-xs text-muted">Alerts remain inside the application. A signal prompts review and does not confirm an outbreak.</p>
        </section>
    </div>
    <section class="patient-card space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 class="text-lg font-semibold">Development testing</h2><p class="mt-2 max-w-3xl text-sm text-muted">Preview the saved settings using fictional case counts. This tests the statistical comparison; patient classification and alert review are exercised in the separate fictional demo.</p></div>
            <button type="button" wire:click="testDetection" wire:loading.attr="disabled" class="patient-button bg-brand-blue">Run Test Detection</button>
        </div>
        <p class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">SIMULATED RESULTS ONLY · No patient records, surveillance results, or alerts are saved. Save any sensitivity changes before testing.</p>
        @unless ($settings['settings_reviewed'])<p class="text-sm text-amber-900">Save a reviewed sensitivity first. Until then, tests report insufficient data.</p>@endunless
        <p wire:loading wire:target="testDetection" role="status" class="text-sm text-muted">Running simulated detection…</p>
        @if ($simulation)
            <div role="status" class="space-y-2 text-sm"><p class="font-semibold">Test completed · {{ $simulation['completed_at'] }} · {{ ucfirst($simulation['sensitivity']) }} sensitivity</p><p>{{ $simulation['cases'] }} simulated current cases evaluated · {{ $simulation['historical_cases'] }} simulated historical cases · {{ $simulation['test_alerts'] }} test alert previews</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-blush"><tr><th class="p-3">Simulated scenario</th><th class="p-3">Current cases</th><th class="p-3">Expected cases</th><th class="p-3">Test result</th></tr></thead><tbody class="divide-y divide-line">@foreach ($simulation['results'] as $result)<tr><td class="p-3">{{ $result['name'] }}</td><td class="p-3">{{ $result['observed'] }}</td><td class="p-3">{{ $result['expected'] !== null ? number_format($result['expected'], 2) : 'Not enough data' }}</td><td class="p-3"><x-surveillance-outcome :outcome="$result['outcome']" /></td></tr>@endforeach</tbody></table></div>
        @else<p class="text-sm text-muted">No test run yet. The preview includes stable counts, an unusual increase, and missing history.</p>@endif
    </section>
    <details wire:ignore.self class="patient-card text-sm">
        <summary class="cursor-pointer font-semibold">Statistical Details</summary>
        <p class="mt-3 text-muted">The existing attendance-adjusted Poisson model automatically switches to a negative binomial model when historical variability is high. Reliability checks and the same approved syndrome version are required. Bonferroni correction adjusts for the categories tested in each run. Repeated, overlapping windows remain correlated; these thresholds do not control false alerts across all future runs.</p>
        <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-xs"><thead><tr><th class="p-2">Sensitivity</th><th class="p-2">Alert threshold</th><th class="p-2">Watch threshold</th></tr></thead><tbody>@foreach ($profiles as $profile)<tr><td class="p-2">{{ $profile['label'] }}</td><td class="p-2">{{ $profile['alpha'] }}</td><td class="p-2">{{ $profile['watch_alpha'] }}</td></tr>@endforeach</tbody></table></div>
        <p class="mt-3 text-muted">Existing internal reliability, episode separation, historical comparison, and scheduling settings are preserved. All-zero history and unreliable or insufficient records produce “Insufficient Data”. Simulated previews assume complete symptom coverage and three fictional categories; they do not validate clinical rules.</p>
    </details>
</div>
