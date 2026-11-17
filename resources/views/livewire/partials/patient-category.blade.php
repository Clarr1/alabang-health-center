@php($categories = app(\App\Services\PatientCategories::class))
<section class="patient-card relative" aria-labelledby="patient-category-title">
    <h2 id="patient-category-title" class="text-sm font-semibold">Patient category</h2>
    @if ($automaticCategory ?? false)
        <p class="mt-2 text-sm" role="status" aria-live="polite">{{ $patientCategory ?: 'Enter a valid birthdate to determine the category.' }}</p>
        <p class="mt-2 text-xs text-muted">The category is calculated automatically from the patient's birthdate.</p>
        <x-input-error :messages="$errors->get('patientCategory')" class="mt-2" />
    @else
        <p class="mt-2 text-sm">{{ $patientCategory ?: 'Not recorded' }}</p>
        @if (isset($patientId) && $originalPatientCategory === null && $patientCategory !== null)
            <p class="mt-2 text-xs text-muted">This existing record has no saved category yet. The suggestion is recorded when registration staff save the profile.</p>
        @endif
        @if ($categories->age($birthdate) !== null)
            <p class="mt-2 text-xs text-muted">Calculated age: {{ $categories->age($birthdate) }}. Suggested category: {{ $this->suggestedCategory ?: 'No configured category covers this age' }}.</p>
        @endif
        @if ($this->categoryMismatch)
            <div role="alert" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p>The recorded category differs from the current age category. Registration staff can review the birthdate on the Edit Patient page to save the automatic category.</p>
                <p class="mt-2">Recorded exception reason: {{ $categoryOverrideReason ?: 'Not recorded.' }}</p>
            </div>
        @endif
        <x-input-error :messages="$errors->get('patientCategory')" class="mt-2" />
        <x-input-error :messages="$errors->get('categoryOverrideReason')" class="mt-2" />
    @endif
</section>
