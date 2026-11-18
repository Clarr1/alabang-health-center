<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PatientMedicalRecordPdf
{
    /** @return array{record: MedicalRecord, patient: PatientInformation, fullName: string, familyNumber: ?string, generatedAt: Carbon, ageAtVisit: ?int} */
    public function data(MedicalRecord $record): array
    {
        Gate::authorize('queue-view');
        $record = MedicalRecord::query()->where('patient_id', $record->patient_id)->findOrFail($record->getKey());
        $patientId = $record->patient_id;
        $queueId = $record->queue_id;
        $record->load([
            'patient.household', 'handledBy', 'symptoms',
            'vitalSign' => fn (BelongsTo $vital): BelongsTo => $vital->where('patient_id', $patientId)
                ->when($queueId !== null, fn (Builder $query): Builder => $query->where('queue_id', $queueId))->with('recordedBy'),
            'followUp' => fn (HasOne $followUp): HasOne => $followUp->where('patient_id', $patientId),
        ]);
        $patient = $record->patient;
        abort_if($patient === null, 404);
        $visitDate = $record->consultation_date ?? $record->encounter_at;

        return [
            'record' => $record,
            'patient' => $patient,
            'fullName' => trim(implode(' ', array_filter([$patient->first_name, $patient->middle_name, $patient->last_name]))),
            'familyNumber' => $patient->household?->family_number,
            'generatedAt' => now('Asia/Manila'),
            'ageAtVisit' => $visitDate === null ? null : app(PatientCategories::class)->age($patient->birthdate, $visitDate),
        ];
    }

    /** @return array<string, string> */
    public function consultationFields(MedicalRecord $record): array
    {
        $fields = ['Chief complaint' => $record->chief_complaint, 'Symptoms' => implode(', ', $record->symptomNames())];
        foreach (IndividualTreatmentRecord::EXAMINATIONS as $group => $options) {
            $examination = $record->physical_examination[$group] ?? [];
            $findings = array_filter([...($examination['findings'] ?? []), $examination['other'] ?? null], fn (mixed $value): bool => filled($value));
            if ($findings !== []) {
                $label = $group === 'heent' ? 'HEENT' : ucwords(str_replace('_', ' / ', $group));
                $fields['Physical examination - '.$label] = implode(', ', $findings);
            }
        }
        foreach (IndividualTreatmentRecord::NOTES as $key => $label) {
            $fields[$label] = $record->{$key};
        }
        $fields += [
            'Diagnosis' => $record->diagnosis, 'Laboratory requests' => $record->laboratory_requests,
            'Prescription' => $record->prescription, 'Treatment' => $record->treatment,
            'Consultation notes' => $record->consultation_notes,
            'Recorded follow-up date' => $record->follow_up_date?->format('d M Y'), "Doctor's name" => $record->handledBy?->name,
        ];

        return array_filter($fields, fn (mixed $value): bool => filled($value));
    }

    public function document(MedicalRecord $record): PdfDocument
    {
        $data = $this->data($record);
        $record = $data['record'];
        $pdf = Pdf::loadView('medical-records.pdf', $data)
            ->setPaper('a4')->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $bold = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans', 'bold');
        $canvas->page_text(48, 24, 'Alabang Health Center', $bold, 14, [0.15, 0.2, 0.28]);
        $visitDate = ($record->consultation_date ?? $record->encounter_at?->timezone('Asia/Manila'))?->format('d M Y') ?? 'Date not recorded';
        $canvas->page_text(48, 45, 'Visit '.$visitDate.' | '.Str::limit($data['fullName'], 45), $font, 8, [0.35, 0.4, 0.45]);
        $canvas->page_text(48, 58, 'Family Number: '.Str::limit($data['familyNumber'] ?: 'Not assigned', 90), $font, 8, [0.35, 0.4, 0.45]);
        $canvas->page_line(48, 73, 547, 73, [0.57, 0.73, 0.79], 0.7);
        $canvas->page_line(48, 800, 547, 800, [0.8, 0.83, 0.87], 0.5);
        $canvas->page_text(48, 811, 'Confidential medical information. For authorized healthcare use only.', $font, 7, [0.35, 0.4, 0.45]);
        $canvas->page_text(450, 811, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.35, 0.4, 0.45]);

        return $pdf;
    }
}
