@php
    $patient = $record->patient;
    $vitalSign = $record->vitalSign;
    $sections = [
        'Patient Information' => [
            'Patient ID' => $record->patient_id,
            'First name' => $patient?->first_name,
            'Middle name' => $patient?->middle_name,
            'Last name' => $patient?->last_name,
            'Birthdate' => $patient?->birthdate,
            'Age' => $patient?->age,
            'Sex' => $patient?->sex,
            'Address' => $patient?->address,
            'Contact number' => $patient?->contact_number,
            'Emergency contact' => $patient?->emergency_contact,
            'Registration date' => $patient?->registration_date,
            'Status' => $patient?->status,
            'Created' => $patient?->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
            'Last updated' => $patient?->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
        ],
        'Vital Signs' => [
            'Recording date' => $vitalSign?->recorded_at?->timezone('Asia/Manila')->format('M j, Y'),
            'Recording time' => $vitalSign?->recorded_at?->timezone('Asia/Manila')->format('g:i A'),
            'Recorded by' => $vitalSign?->recordedBy?->name,
            'Blood pressure' => filled($vitalSign?->blood_pressure) ? $vitalSign->blood_pressure.' mmHg' : null,
            'Temperature' => $vitalSign?->temperature !== null ? $vitalSign->temperature.' °C' : null,
            'Pulse rate' => $vitalSign?->pulse_rate !== null ? $vitalSign->pulse_rate.' bpm' : null,
            'Respiratory rate' => $vitalSign?->respiratory_rate !== null ? $vitalSign->respiratory_rate.' breaths/min' : null,
            'Height' => $vitalSign?->height !== null ? $vitalSign->height.' cm' : null,
            'Weight' => $vitalSign?->weight !== null ? $vitalSign->weight.' kg' : null,
            'BMI' => $vitalSign?->bmi,
        ],
        "Doctor's Consultation" => [
            'Attending doctor' => $record->handledBy?->name,
            'Consultation date' => $record->consultation_date?->format('M j, Y'),
            'Chief complaint' => $record->consultation_notes,
            'Diagnosis' => $record->diagnosis,
            'Treatment plan' => $record->treatment,
            'Prescription' => $record->prescription,
            'Follow-up date' => $record->follow_up_date?->format('M j, Y'),
            'Created' => $record->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A'),
        ],
    ];
@endphp

<div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Medical Record Details</h1>
            <p class="mt-2 text-sm text-gray-600">Record #{{ $record->medical_record_id }}</p>
        </div>
        <a href="{{ $backUrl }}" wire:navigate class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Back to Medical Records</a>
    </div>

    @foreach ($sections as $title => $fields)
        <section aria-labelledby="record-section-{{ $loop->index }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 id="record-section-{{ $loop->index }}" class="border-b border-gray-200 pb-4 text-lg font-semibold text-gray-900">{{ $title }}</h2>
            <dl class="mt-5 grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($fields as $label => $value)
                    <div @class(['min-w-0', 'sm:col-span-2 lg:col-span-3' => in_array($label, ['Address', 'Chief complaint', 'Diagnosis', 'Treatment plan', 'Prescription'], true)])>
                        <dt class="font-medium text-gray-500">{{ $label }}</dt>
                        <dd class="mt-1 whitespace-pre-line break-words text-gray-900">{{ filled($value) ? $value : 'Not provided.' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
