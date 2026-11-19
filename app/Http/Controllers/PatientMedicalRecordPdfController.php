<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Services\IndividualTreatmentRecord;
use App\Services\PatientMedicalRecordPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PatientMedicalRecordPdfController extends Controller
{
    public function show(Request $request, PatientInformation $patient, MedicalRecord $record, PatientMedicalRecordPdf $export): Response
    {
        $this->authorizeVisit($request, $patient, $record);
        $pdf = $export->document($record);
        IndividualTreatmentRecord::audit($patient->getKey(), $record->queue_id, 'medical-records-pdf-generated', ['medical_record_id' => $record->getKey(), 'delivery' => 'inline']);

        return $this->privateResponse($pdf->stream('medical-record-'.$record->getKey().'.pdf'));
    }

    public function download(Request $request, PatientInformation $patient, MedicalRecord $record, PatientMedicalRecordPdf $export): Response
    {
        $this->authorizeVisit($request, $patient, $record);
        $pdf = $export->document($record);
        IndividualTreatmentRecord::audit($patient->getKey(), $record->queue_id, 'medical-records-pdf-generated', ['medical_record_id' => $record->getKey(), 'delivery' => 'download']);

        return $this->privateResponse($pdf->download('medical-record-'.$record->getKey().'.pdf'));
    }

    public function print(Request $request, PatientInformation $patient, MedicalRecord $record): Response
    {
        $this->authorizeVisit($request, $patient, $record);

        return $this->privateResponse(response()->view('medical-records.print', [
            'patient' => $patient, 'record' => $record, 'parameters' => ['patient' => $patient, 'record' => $record, ...$request->only('familyHouseholdId')],
        ]));
    }

    private function authorizeVisit(Request $request, PatientInformation $patient, MedicalRecord $record): void
    {
        Gate::authorize('medical-records-view');
        abort_unless((int) $record->patient_id === (int) $patient->getKey(), 404);
        $validated = $request->validate(['familyHouseholdId' => ['nullable', 'integer', 'min:1']]);
        if (isset($validated['familyHouseholdId'])) {
            abort_unless((int) $patient->household_id === (int) $validated['familyHouseholdId'], 404);
        }
    }

    private function privateResponse(Response $response): Response
    {
        return $response->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
