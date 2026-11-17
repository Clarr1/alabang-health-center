@props(['editing' => false, 'reviewing' => false, 'details' => []])

@if ($reviewing)
    <section class="patient-card relative" aria-labelledby="patient-review-title">
        <h2 id="patient-review-title" class="text-base font-semibold">Review patient details</h2>
        <p class="mt-1 text-xs text-muted">Confirm these details with the patient before {{ $editing ? 'saving changes' : 'registering' }}.</p>
        <dl class="mt-5 grid gap-5 sm:grid-cols-3">
            @foreach ([
                'first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name',
                'birthdate' => 'Birthdate', 'age' => 'Age', 'sex' => 'Sex', 'address' => 'Full address',
                'contact_number' => 'Mobile number', 'emergency_contact' => 'Emergency contact',
                'registration_date' => 'Registration date', 'surveillance_residence' => 'Verified surveillance residence',
            ] as $field => $label)
                <div @class(['min-w-0', 'sm:col-span-3' => $field === 'address'])><dt class="text-xs text-muted">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line break-words text-sm font-medium">{{ filled($details[$field] ?? null) ? $details[$field] : 'Not provided.' }}</dd></div>
            @endforeach
            <div><dt class="text-xs text-muted">Record status</dt><dd class="mt-1 text-sm font-medium">{{ $editing ? $details['status'] : 'Active on registration' }}</dd></div>
        </dl>
    </section>
@else
    <section class="patient-card relative" aria-labelledby="patient-information-title">
        <h2 id="patient-information-title" class="text-base font-semibold">Patient information</h2>
        <p class="mt-1 text-xs text-muted">Use the patient's full name and correct birthdate for reliable record matching.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            @foreach ([
                'first_name' => ['First name', 'text'],
                'middle_name' => ['Middle name', 'text'],
                'last_name' => ['Last name', 'text'],
                'birthdate' => ['Birthdate', 'date'],
                'age' => ['Age (calculated)', 'number'],
                'sex' => ['Sex', 'select'],
            ] as $field => [$label, $type])
                <div class="min-w-0">
                    <label for="patient-{{ $field }}" class="patient-label">{{ $label }} @if ($field !== 'middle_name' && $field !== 'age')<span aria-hidden="true">*</span>@endif</label>
                    @if ($type === 'select')
                        <select id="patient-{{ $field }}" wire:model="{{ $field }}" required aria-describedby="patient-{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" class="patient-input w-full"><option value="Male">Male</option><option value="Female">Female</option></select>
                    @elseif ($field === 'age')
                        <input id="patient-age" type="number" wire:model="age" min="0" max="150" readonly placeholder="Enter birthdate first" aria-describedby="patient-age-error" aria-invalid="{{ $errors->has('age') ? 'true' : 'false' }}" class="patient-input w-full bg-[#e9ebf0]">
                    @else
                        <input id="patient-{{ $field }}" type="{{ $type }}"
                               @if (in_array($field, ['first_name', 'last_name'], true)) wire:model.live.debounce.400ms="{{ $field }}"
                               @elseif ($field === 'birthdate') wire:model.live="birthdate"
                               @else wire:model="{{ $field }}" @endif
                               @if ($type === 'text') maxlength="255" @endif
                               @if ($field !== 'middle_name') required @endif
                               @if ($field === 'birthdate') max="{{ now('Asia/Manila')->toDateString() }}" @endif
                               aria-describedby="patient-{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" class="patient-input w-full">
                    @endif
                    <x-input-error id="patient-{{ $field }}-error" :messages="$errors->get($field)" class="mt-1" />
                </div>
            @endforeach
        </div>
        <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="patient-address" class="patient-label">Full address <span aria-hidden="true">*</span></label>
                <textarea id="patient-address" wire:model="address" rows="3" required placeholder="House / building / street, purok / sitio, barangay, city, province / region" aria-describedby="patient-address-help patient-address-error" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" class="patient-input w-full"></textarea>
                <p id="patient-address-help" class="mt-1 text-[11px] text-muted">Include the street, barangay, city, and province or region.</p>
                <x-input-error id="patient-address-error" :messages="$errors->get('address')" class="mt-1" />
                <label for="surveillance-residence" class="patient-label mt-4">Verified residence for surveillance</label>
                <select id="surveillance-residence" wire:model="surveillance_residence" class="patient-input w-full">
                    <option value="Unverified">Unverified - exclude from surveillance</option>
                    <option value="Alabang">Confirmed Barangay Alabang, Muntinlupa City</option>
                    <option value="Outside Scope">Confirmed outside surveillance scope</option>
                </select>
                <p class="mt-1 text-xs text-muted">Confirm barangay and city with the patient. Only verified Alabang residents are counted. Verify again when an address changes.</p>
                <x-input-error :messages="$errors->get('surveillance_residence')" class="mt-1" />
            </div>
            <div class="space-y-4">
                <div><label for="patient-registration_date" class="patient-label">Registration date <span aria-hidden="true">*</span></label><input id="patient-registration_date" type="date" wire:model="registration_date" required aria-describedby="patient-registration_date-error" aria-invalid="{{ $errors->has('registration_date') ? 'true' : 'false' }}" class="patient-input w-full"><x-input-error id="patient-registration_date-error" :messages="$errors->get('registration_date')" class="mt-1" /></div>
                @if ($editing)
                    <div><label for="patient-status" class="patient-label">Record status <span aria-hidden="true">*</span></label><select id="patient-status" wire:model="status" required aria-describedby="patient-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" class="patient-input w-full"><option value="Active">Active</option><option value="Inactive">Inactive</option><option value="Follow-up">Follow-up</option></select><x-input-error id="patient-status-error" :messages="$errors->get('status')" class="mt-1" /></div>
                @endif
            </div>
        </div>
    </section>

    <section class="patient-card relative" aria-labelledby="patient-contact-title">
        <h2 id="patient-contact-title" class="text-base font-semibold">Contact details</h2>
        <p class="mt-1 text-xs text-muted">Mobile number and emergency contact are required. Confirm these details with the patient.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <div>
                <label for="patient-contact_number" class="patient-label">Mobile number <span aria-hidden="true">*</span></label>
                <input id="patient-contact_number" type="tel" maxlength="20" required wire:model="contact_number" aria-describedby="patient-contact-help patient-contact_number-error" aria-invalid="{{ $errors->has('contact_number') ? 'true' : 'false' }}" class="patient-input w-full">
                <p id="patient-contact-help" class="mt-1 text-[11px] text-muted">Enter a number the patient can be reached on.</p>
                <x-input-error id="patient-contact_number-error" :messages="$errors->get('contact_number')" class="mt-1" />
            </div>
            <div class="sm:col-span-2">
                <label for="patient-emergency_contact" class="patient-label">Emergency contact <span aria-hidden="true">*</span></label>
                <input id="patient-emergency_contact" type="text" maxlength="255" required wire:model="emergency_contact" placeholder="Name, relationship, and mobile number" aria-describedby="patient-emergency-help patient-emergency_contact-error" aria-invalid="{{ $errors->has('emergency_contact') ? 'true' : 'false' }}" class="patient-input w-full">
                <p id="patient-emergency-help" class="mt-1 text-[11px] text-muted">Keep the name, relationship, and emergency number together.</p>
                <x-input-error id="patient-emergency_contact-error" :messages="$errors->get('emergency_contact')" class="mt-1" />
            </div>
        </div>
    </section>
@endif
