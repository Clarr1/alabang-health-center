@props(['editing' => false])

<div class="grid gap-5 md:grid-cols-2">
    @foreach ([
        'registration_date' => ['Registration Date', 'date'],
        'first_name' => ['First Name', 'text'],
        'last_name' => ['Last Name', 'text'],
        'middle_name' => ['Middle Name', 'text'],
        'sex' => ['Sex', 'select'],
        'birthdate' => ['Birthdate', 'date'],
        'age' => ['Age', 'number'],
        'address' => ['Address', 'textarea'],
        'contact_number' => ['Contact Number', 'tel'],
        'emergency_contact' => ['Emergency Contact', 'text'],
    ] as $field => [$label, $type])
        <div @class(['md:col-span-2' => $field === 'address'])>
            <label for="patient-{{ $field }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</label>
            @if ($type === 'select')
                <select id="patient-{{ $field }}" wire:model="{{ $field }}" aria-describedby="patient-{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            @elseif ($type === 'textarea')
                <textarea id="patient-{{ $field }}" wire:model="{{ $field }}" rows="3" aria-describedby="patient-{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
            @else
                <input id="patient-{{ $field }}" type="{{ $type }}" wire:model="{{ $field }}" @if ($type === 'number') min="0" max="150" @endif aria-describedby="patient-{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            @endif
            <x-input-error id="patient-{{ $field }}-error" :messages="$errors->get($field)" class="mt-1" />
        </div>
    @endforeach

    @if ($editing)
        <div>
            <label for="patient-status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
            <select id="patient-status" wire:model="status" aria-describedby="patient-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Follow-up">Follow-up</option>
            </select>
            <x-input-error id="patient-status-error" :messages="$errors->get('status')" class="mt-1" />
        </div>
    @endif
</div>
