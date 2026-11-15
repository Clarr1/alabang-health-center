@php($categories = app(\App\Services\PatientCategories::class))
<section class="patient-card relative" aria-labelledby="patient-category-title">
    <h2 id="patient-category-title" class="text-sm font-semibold">Patient category</h2>
    @if (($allowCategorySelection ?? false) && ! $reviewing && Gate::allows('queue-register'))
        <label for="patient-category" class="patient-label mt-3">Registration category</label>
        <select id="patient-category" wire:model.live="patientCategory" class="patient-input w-full"><option value="">Not recorded</option>@foreach ($categories->choices() as $category => $range)<option value="{{ $category }}">{{ $category }} ({{ $range['min_age'] }}{{ $category === 'Adult' ? '+' : '–'.$range['max_age'] }} years)</option>@endforeach</select>
    @else
        <p class="mt-2 text-sm">{{ $patientCategory ?: 'Not recorded' }}</p>
    @endif
    @if (isset($patientId) && $originalPatientCategory === null && $patientCategory !== null)
        <p class="mt-2 text-xs text-muted">This existing record has no saved category yet. The suggestion is recorded when registration staff save the profile.</p>
    @endif
    @if ($categories->age($birthdate) !== null)
        <p class="mt-2 text-xs text-muted">Calculated age: {{ $categories->age($birthdate) }}. Suggested category: {{ $this->suggestedCategory ?: 'No configured category covers this age' }}.</p>
    @endif
    @if ($this->categoryMismatch)
        <div role="alert" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            <p>The selected category does not match the calculated age. The category will stay {{ $patientCategory }} unless you change it.</p>
            @if ($reviewing || Gate::denies('patient-category-override'))
                <p class="mt-2">Exception reason: {{ $categoryOverrideReason ?: 'A documented exception is required before saving.' }}</p>
            @else
                <label for="category-override-reason" class="patient-label mt-3">Reason for category exception *</label>
                <textarea id="category-override-reason" wire:model.blur="categoryOverrideReason" rows="2" minlength="10" maxlength="1000" class="patient-input w-full" placeholder="Explain why this registration category is appropriate."></textarea>
            @endif
        </div>
    @endif
    <x-input-error :messages="$errors->get('patientCategory')" class="mt-2" />
    <x-input-error :messages="$errors->get('categoryOverrideReason')" class="mt-2" />
</section>
