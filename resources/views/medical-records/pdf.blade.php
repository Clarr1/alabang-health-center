<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Alabang Health Center - Medical Record - {{ $fullName }}</title>
    <style>
        @page { size: A4 portrait; margin: 29mm 17mm 20mm; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.5; color: #263447; }
        h1 { font-size: 18pt; margin: 0 0 1mm; }
        h2 { font-size: 12pt; margin: 6mm 0 3mm; padding: 2mm 3mm; background: #edf3f6; border-left: 2pt solid #739cae; page-break-after: avoid; }
        h3 { font-size: 10pt; margin: 4mm 0 2mm; border-bottom: 0.5pt solid #ced5dd; padding-bottom: 1mm; page-break-after: avoid; }
        p { margin: 1mm 0 2mm; }
        .muted { color: #536171; font-size: 8pt; }
        .field-label { font-weight: bold; margin-top: 2.5mm; page-break-after: avoid; }
        .value { white-space: pre-wrap; overflow-wrap: break-word; word-wrap: break-word; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 2mm 0 3mm; }
        tr { page-break-inside: avoid; }
        th, td { border: 0.5pt solid #ced5dd; padding: 1.8mm 2mm; text-align: left; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        th { font-size: 8pt; background: #f4f6f8; }
        .metric { width: 31%; color: #536171; }
        .entry { margin-bottom: 4mm; }
    </style>
</head>
<body>
    <h1>Patient Medical Record</h1>
    <p><strong>Consultation: {{ ($record->consultation_date ?? $record->encounter_at?->timezone('Asia/Manila'))?->format('d M Y') ?: 'Date not recorded' }}</strong></p>
    @if ($record->encounter_at)<p>Encounter date and time: {{ $record->encounter_at->timezone('Asia/Manila')->format('d M Y, h:i A') }}</p>@endif
    <p class="muted">Generated {{ $generatedAt->format('d M Y, h:i A') }} (Asia/Manila). This document contains the selected visit only.</p>
    <h2>A. Patient Information</h2>
    @php
        $demographics = array_filter([
            'Full name' => $fullName, 'Family Number' => $familyNumber ?: 'Not assigned',
            'Date of birth' => app(\App\Services\PatientCategories::class)->birthdate($patient->birthdate)?->format('d M Y'),
            'Age at visit' => $ageAtVisit === null ? null : $ageAtVisit.' years',
            'Sex' => $patient->sex, 'Address' => $patient->address,
        ], fn ($value) => filled($value));
    @endphp
    @foreach ($demographics as $label => $value)
        <div class="field-label">{{ $label }}</div><div class="value">{{ $value }}</div>
    @endforeach
    <h2>B. Visit Vital Signs</h2>
    @php
        $vital = $record->vitalSign;
    @endphp
    @if ($vital)
        <h3>Examination: {{ $vital->recorded_at?->timezone('Asia/Manila')->format('d M Y, h:i A') ?: 'Date not recorded' }}</h3>
        @php
            $measurements = [];
            foreach ([
                'blood_pressure' => ['Blood pressure', ' mmHg'], 'temperature' => ['Temperature', ' °C'],
                'height' => ['Height', ' cm'], 'weight' => ['Weight', ' kg'], 'bmi' => ['BMI', ' kg/m²'],
                'nutritional_status' => ['BMI / nutrition classification (recorded)', ''],
                'nutrition_assessment_method' => ['Assessment method', ''], 'pulse_rate' => ['Pulse rate', ' bpm'],
                'respiratory_rate' => ['Respiratory rate', ' breaths/min'], 'oxygen_saturation' => ['Oxygen saturation', ' %'],
                'waistline' => ['Waistline', ' cm'],
            ] as $key => [$label, $unit]) {
                if (filled($vital->{$key})) { $measurements[$label] = $vital->{$key}.$unit; }
            }
        @endphp
        @if ($measurements !== [])
            <table aria-label="Examination measurements"><tbody>
                @foreach ($measurements as $label => $value)<tr><th class="metric">{{ $label }}</th><td>{{ $value }}</td></tr>@endforeach
            </tbody></table>
        @else
            <p class="muted">No measurements documented for this examination.</p>
        @endif
        @if (filled($vital->recordedBy?->name))<p class="muted">Recorded by: {{ $vital->recordedBy->name }}</p>@endif
    @else
        <p class="muted">No vital signs linked to this visit.</p>
    @endif
    <h2>C. Doctor's Consultation</h2>
        @forelse (app(\App\Services\PatientMedicalRecordPdf::class)->consultationFields($record) as $label => $value)
            <div class="field-label">{{ $label }}</div><div class="value">{{ $value }}</div>
        @empty
            <p class="muted">No clinical details documented for this consultation.</p>
        @endforelse
    @if ($record->followUp)
        <h2>D. Visit Follow-up</h2>
        @php
            $appointment = $record->followUp;
        @endphp
        <p><strong>{{ $appointment->scheduled_at?->timezone('Asia/Manila')->format('d M Y, h:i A') ?: 'Date not recorded' }}</strong>@if (filled($appointment->status)) | {{ $appointment->status }}@endif</p>
        @if (filled($appointment->purpose))<div class="field-label">Purpose</div><div class="value">{{ $appointment->purpose }}</div>@endif
        @if (filled($appointment->internal_notes))<div class="field-label">Staff notes</div><div class="value">{{ $appointment->internal_notes }}</div>@endif
    @endif
</body>
</html>
