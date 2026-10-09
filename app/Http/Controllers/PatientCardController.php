<?php

namespace App\Http\Controllers;

use App\Models\PatientInformation;
use App\Services\PatientCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PatientCardController extends Controller
{
    public function show(PatientInformation $patient, PatientCard $card): Response
    {
        return response()->view('patient-cards.document', $card->data($patient))
            ->header('Cache-Control', 'private, no-store');
    }

    public function download(PatientInformation $patient, PatientCard $card): Response
    {
        return Pdf::loadView('patient-cards.document', $card->data($patient))
            ->setPaper([0, 0, PatientCard::WIDTH_MM * 72 / 25.4, PatientCard::HEIGHT_MM * 72 / 25.4])
            ->download('patient-card-'.$patient->patient_id.'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }
}
