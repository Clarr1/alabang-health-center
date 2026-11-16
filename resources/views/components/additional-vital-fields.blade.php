<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <div>
        <label for="oxygen_saturation" class="patient-label">Oxygen Saturation (O2 Sat, %)</label>
        <input id="oxygen_saturation" type="number" min="0" max="100" step="0.01" wire:model="oxygen_saturation" class="patient-input w-full">
        <x-input-error :messages="$errors->get('oxygen_saturation')" class="mt-2" />
    </div>
    <div>
        <label for="waistline" class="patient-label">Waistline (WLINE, cm)</label>
        <input id="waistline" type="number" min="0.1" max="300" step="0.01" wire:model="waistline" class="patient-input w-full">
        <x-input-error :messages="$errors->get('waistline')" class="mt-2" />
    </div>
    <div>
        <label for="nutrition_assessment_method" class="patient-label">Nutrition assessment method</label>
        <select id="nutrition_assessment_method" wire:model="nutrition_assessment_method" class="patient-input w-full">
            <option value="">Not assessed</option>
            @foreach ($this->nutritionAssessmentMethods() as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach
        </select>
        <x-input-error :messages="$errors->get('nutrition_assessment_method')" class="mt-2" />
    </div>
    <div>
        <label for="nutritional_status" class="patient-label">Nutritional Status</label>
        <input id="nutritional_status" wire:model="nutritional_status" maxlength="255" class="patient-input w-full">
        <x-input-error :messages="$errors->get('nutritional_status')" class="mt-2" />
    </div>
</div>
<p class="mt-3 text-xs text-muted">Document the assessed status and method. For children and adolescents, use an age-specific growth assessment; adult BMI categories are not applied.</p>
