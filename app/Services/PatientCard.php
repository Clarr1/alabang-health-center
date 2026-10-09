<?php

namespace App\Services;

use App\Models\PatientInformation;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Generator;

class PatientCard
{
    public const WIDTH_MM = 85.60;

    public const HEIGHT_MM = 53.98;

    /** @return array{patient: PatientInformation, fullName: string, birthdate: string, qrCode: string} */
    public function data(PatientInformation $patient): array
    {
        $qrCode = (new Generator)->format('svg')->size(240)->margin(4)
            ->errorCorrection('M')->generate((string) $patient->patient_id);

        return [
            'patient' => $patient,
            'fullName' => trim(implode(' ', array_filter([
                $patient->first_name, $patient->middle_name, $patient->last_name,
            ]))),
            'birthdate' => $patient->birthdate ? Carbon::parse($patient->birthdate)->format('M d, Y') : 'Not available',
            'qrCode' => 'data:image/svg+xml;base64,'.base64_encode((string) $qrCode),
        ];
    }
}
