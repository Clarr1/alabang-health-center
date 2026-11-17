<section class="patient-card relative" aria-labelledby="patient-category-title">
    <h2 id="patient-category-title" class="text-sm font-semibold">Patient category</h2>
    <p class="mt-2 text-sm" role="status" aria-live="polite">{{ $patientCategory ?: 'Enter a valid birthdate to determine the category.' }}</p>
    <p class="mt-2 text-xs text-muted">The category follows the patient's current age automatically.</p>
    <x-input-error :messages="$errors->get('patientCategory')" class="mt-2" />
</section>
