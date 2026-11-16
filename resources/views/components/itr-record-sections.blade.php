@props(['record'])
<section class="mt-5 rounded-lg border border-line p-4">
    <h4 class="text-sm font-semibold">Individual Treatment Record</h4>
    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="text-muted">Family Number</dt><dd>{{ $record->patient?->household?->family_number ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-muted">Visit date and time</dt><dd>{{ ($record->encounter_at ?? $record->queue?->created_at)?->timezone('Asia/Manila')->format('d M Y, h:i A') ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-muted">Age at visit</dt><dd>{{ $record->patient?->birthdate && $record->consultation_date ? (int) \Illuminate\Support\Carbon::parse($record->patient->birthdate)->diffInYears($record->consultation_date) : 'Not recorded' }}</dd></div>
        @foreach (\App\Services\IndividualTreatmentRecord::NOTES as $field => $label)
            <div class="sm:col-span-2"><dt class="font-medium text-muted">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $record->{$field} ?: 'Not recorded' }}</dd></div>
        @endforeach
    </dl>
    <h5 class="mt-5 text-sm font-semibold">Physical Examination</h5>
    <dl class="mt-3 grid gap-4 text-sm sm:grid-cols-2">
        @foreach (\App\Services\IndividualTreatmentRecord::EXAMINATIONS as $group => $options)
            <div><dt class="font-medium text-muted">{{ $group === 'heent' ? 'HEENT' : ($group === 'chest_lungs' ? 'Chest / Lungs' : ucfirst($group)) }}</dt><dd class="mt-1 whitespace-pre-line break-words">{{ implode(', ', $record->physical_examination[$group]['findings'] ?? []) ?: 'Not recorded' }}@if (filled($record->physical_examination[$group]['other'] ?? null))<span class="mt-1 block">Others: {{ $record->physical_examination[$group]['other'] }}</span>@endif</dd></div>
        @endforeach
    </dl>
</section>
