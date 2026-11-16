@php
    $fieldId = str_replace('.', '-', $prefix.'.'.$key);
    $isVisible = \App\Services\PatientRegistrationSchema::visible($key, $fields, $values);
    $value = $values[$key] ?? null;
@endphp
@if ($isVisible)
    <div wire:key="registration-field-{{ $fieldId }}" class="min-w-0">
        @if ($reviewing)
            <dt class="patient-label">{{ $field['label'] }}</dt><dd class="mt-1 whitespace-pre-line break-words text-sm">{{ filled($value) ? $value : 'Not provided.' }}</dd>
        @else
            <label for="{{ $fieldId }}" class="patient-label">{{ $field['label'] }}</label>
            @if ($field['type'] === 'select')
                <select id="{{ $fieldId }}" wire:model.live="{{ $prefix }}.{{ $key }}" class="patient-input w-full"><option value="">Not provided</option>@foreach ($field['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
            @elseif ($field['type'] === 'textarea')
                <textarea id="{{ $fieldId }}" wire:model.blur="{{ $prefix }}.{{ $key }}" rows="3" maxlength="10000" class="patient-input w-full"></textarea>
            @else
                <input id="{{ $fieldId }}" type="{{ $field['type'] }}" wire:model.blur="{{ $prefix }}.{{ $key }}" @if ($field['type'] === 'number') min="0" step="any" @elseif ($field['type'] !== 'date') maxlength="255" @endif class="patient-input w-full">
            @endif
            <x-input-error :messages="$errors->get($prefix.'.'.$key)" class="mt-1" />
        @endif
    </div>
@endif
