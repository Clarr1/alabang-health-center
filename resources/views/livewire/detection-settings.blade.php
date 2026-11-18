<div class="mx-auto max-w-screen-2xl space-y-6 px-4 py-7 sm:px-7 lg:px-8">
    <header>
        <p class="text-xs font-semibold uppercase tracking-wide text-muted">Syndromic Surveillance</p>
        <h1 class="mt-2 text-2xl font-semibold">Detection Settings</h1>
        <p class="mt-2 text-sm text-muted">Set the minimum qualifying cases for each monitoring category in Barangay Alabang.</p>
    </header>
    <x-surveillance-nav active="surveillance.settings" />
    @if (session('surveillance-status'))<p role="status" class="patient-card text-sm">{{ session('surveillance-status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Development settings: the initial minimum of {{ config('surveillance.minimum_case_threshold') }} cases is for software testing and is not a medically approved threshold. Local clinical validation is required.</p>
    <section class="patient-card space-y-4">
        <h2 class="text-lg font-semibold">Category monitoring settings</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-blush"><tr><th class="p-3">Syndrome / disease category</th><th class="p-3">Minimum cases</th><th class="p-3">Monitoring period</th><th class="p-3">Monitoring status</th></tr></thead>
            <tbody class="divide-y divide-line">@forelse ($categories as $category)
                <tr wire:key="threshold-{{ $category->id }}"><td class="p-3 font-medium">{{ $category->name }}</td><td class="p-3">{{ \App\Models\SurveillanceSetting::minimumCases($category->id, $settings) }}@unless (isset($settings['category_thresholds'][$category->id]))<span class="block text-xs text-muted">Initial development value</span>@endunless</td><td class="p-3">48 Hours (fixed)</td><td class="p-3">{{ $category->is_active ? 'Active' : 'Disabled' }}</td></tr>
            @empty<tr><td colspan="4" class="p-4 text-muted">No categories yet. Add and approve syndrome definitions before monitoring.</td></tr>@endforelse</tbody>
        </table></div>
    </section>
    <div class="grid items-start gap-6 xl:grid-cols-3">
        <form wire:submit="save" class="patient-card space-y-5 xl:col-span-2">
            <h2 class="text-lg font-semibold">Edit a category</h2>
            <div><label for="threshold-category" class="patient-label">Syndrome / disease category</label><select id="threshold-category" wire:model.live="categoryId" class="patient-input w-full" required><option value="">Select a category</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            <div><label for="minimum-cases" class="patient-label">Minimum qualifying cases</label><input id="minimum-cases" type="number" min="1" max="10000" step="1" wire:model="minimumCases" class="patient-input w-full" required aria-describedby="threshold-help"><p id="threshold-help" class="mt-2 text-sm text-muted">Enter 1 to 10,000 cases. Reaching this minimum starts the threshold-based statistical check. It does not confirm an outbreak.</p></div>
            <p class="text-sm"><span class="font-semibold">Monitoring period:</span> Rolling 48 hours (fixed)</p>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="isActive" class="mt-1 rounded"><span>Enable monitoring for this category. An approved syndrome definition is also required.</span></label>
            <button type="submit" wire:loading.attr="disabled" @disabled($categories->isEmpty()) class="patient-button bg-brand-blue">Save monitoring settings</button>
            <p wire:loading wire:target="save" role="status" class="text-sm text-muted">Saving settings...</p>
        </form>
        <section class="patient-card space-y-4">
            <h2 class="text-lg font-semibold">How detection works</h2>
            <p class="text-sm text-muted">The system counts qualifying illness episodes in completed, eligible consultations. When the minimum is reached, it compares those cases with reliable historical records.</p>
            <p class="text-sm text-muted">A significant unusual increase below the minimum can still produce a separate signal for review. Missing or unreliable history is reported as insufficient data.</p>
            <p class="text-sm text-muted">Historical comparisons, statistical calculations, and duplicate prevention are managed automatically. Changing a minimum affects subsequent checks; saved alert results and reviews retain their original values.</p>
            <p class="border-t border-line pt-4 text-xs text-muted">Barangay Alabang only. Alerts remain inside the application. No email or SMS notifications are sent by this engine.</p>
        </section>
    </div>
    <section class="patient-card space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 class="text-lg font-semibold">Development testing</h2><p class="mt-2 max-w-3xl text-sm text-muted">Preview the selected category's saved minimum using fictional counts below, at, and above that minimum, a stable pattern, and missing history. These previews do not validate clinical thresholds.</p></div>
            <button type="button" wire:click="testDetection" wire:loading.attr="disabled" @disabled($categories->isEmpty()) class="patient-button bg-brand-blue">Run Test Detection</button>
        </div>
        <p class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">SIMULATED RESULTS ONLY. No patient records, surveillance results, or alerts are saved. Save settings before testing. Previews can evaluate disabled categories without enabling actual monitoring.</p>
        @unless ($settings['settings_reviewed'])<p class="text-sm text-amber-900">Save monitoring settings first. Until then, tests report insufficient data.</p>@endunless
        <p wire:loading wire:target="testDetection" role="status" class="text-sm text-muted">Running simulated detection...</p>
        @if ($simulation)
            <div role="status" class="space-y-2 text-sm"><p class="font-semibold">Test completed: {{ $simulation['completed_at'] }}. {{ $simulation['category'] }}: saved minimum {{ $simulation['minimum_cases'] }}; monitoring {{ $simulation['is_active'] ? 'active' : 'disabled' }}.</p><p>{{ $simulation['cases'] }} simulated current cases evaluated; {{ $simulation['historical_cases'] }} simulated historical cases; {{ $simulation['test_alerts'] }} threshold alert previews; {{ $simulation['test_signals'] }} below-minimum signal previews.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-blush"><tr><th class="p-3">Simulated scenario</th><th class="p-3">Current cases</th><th class="p-3">Minimum reached?</th><th class="p-3">Historical expected cases</th><th class="p-3">Test result</th></tr></thead><tbody class="divide-y divide-line">@foreach ($simulation['results'] as $result)<tr><td class="p-3">{{ $result['name'] }}</td><td class="p-3">{{ $result['observed'] }}</td><td class="p-3">{{ $result['threshold_reached'] ? 'Yes' : 'No' }}</td><td class="p-3">{{ $result['expected'] !== null ? number_format($result['expected'], 2) : 'Not enough data' }}</td><td class="p-3"><x-surveillance-outcome :outcome="$result['outcome']" :threshold-reached="$result['threshold_reached']" /></td></tr>@endforeach</tbody></table></div>
        @else<p class="text-sm text-muted">No test run yet. Patient classification and alert review remain available through the existing isolated fictional demo.</p>@endif
    </section>
</div>
