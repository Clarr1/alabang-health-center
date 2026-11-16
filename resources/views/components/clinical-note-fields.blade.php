@props(['fields'])
@foreach ($fields as $field)
    <div class="mt-4">
        <label for="clinical-{{ $field }}" class="patient-label">{{ \App\Services\IndividualTreatmentRecord::NOTES[$field] }}</label>
        <textarea id="clinical-{{ $field }}" wire:model="clinicalNotes.{{ $field }}" rows="3" maxlength="10000" class="patient-input w-full"></textarea>
        <x-input-error :messages="$errors->get('clinicalNotes.'.$field)" class="mt-2" />
    </div>
@endforeach
