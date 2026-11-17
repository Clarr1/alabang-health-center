<?php

namespace App\Services;

use App\Models\PatientInformation;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Generator;

class PatientCard
{
    public const WIDTH_MM = 85.60;

    public const HEIGHT_MM = 53.98;

    /** @return array{patient: PatientInformation, familyNumber: ?string, fullName: string, birthdate: string, qrCode: string, logos: array<string, string>} */
    public function data(PatientInformation $patient): array
    {
        $familyNumber = $patient->household()->value('family_number');
        $qrCode = (new Generator)->format('svg')->size(240)->margin(4)
            ->errorCorrection('M')->generate((string) $patient->patient_id);

        $logos = [];

        foreach (['barangay-alabang', 'city-of-muntinlupa', 'department-of-health'] as $logo) {
            $logos[$logo] = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/'.$logo.'.png')));
        }

        return [
            'patient' => $patient,
            'familyNumber' => filled($familyNumber) ? $familyNumber : null,
            'logos' => $logos,
            'fullName' => trim(implode(' ', array_filter([
                $patient->first_name, $patient->middle_name, $patient->last_name,
            ]))),
            'birthdate' => $patient->birthdate ? Carbon::parse($patient->birthdate)->format('M d, Y') : 'Not available',
            'qrCode' => 'data:image/svg+xml;base64,'.base64_encode((string) $qrCode),
        ];
    }
}
