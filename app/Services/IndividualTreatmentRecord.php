<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class IndividualTreatmentRecord
{
    /** @var array<string, string> */
    public const NOTES = [
        'past_medical_history' => 'PMHX - Past Medical History',
        'family_medical_history' => 'FMHX - Family Medical History',
        'past_surgical_history' => 'PSHX - Past Surgical History',
        'other_history' => 'Other History',
        'subjective_notes' => 'Subjective Notes (S)',
        'other_pe_findings' => 'Other Pertinent PE Findings',
        'laboratory_results' => 'Laboratory Results',
        'ancillary_results' => 'Ancillary Results',
        'diagnostic_findings' => 'Additional Diagnostic Findings',
        'assessment_notes' => 'Assessment Notes (A)',
        'referrals' => 'Referrals',
        'follow_up_instructions' => 'Follow-up Instructions',
    ];

    /** @var array<string, array<int, string>> */
    public const EXAMINATIONS = [
        'skin' => ['Pallor', 'Rashes', 'Jaundice', 'Good Skin Turgor'],
        'heent' => ['Anicteric Sclerae', 'Intact Tympanic Membrane', 'Pupils Briskly Reactive to Light', 'Alar Flaring', 'Aural Discharge', 'Nasal Discharge', 'Tonsillopharyngeal Congestion', 'Exudates', 'Hypertrophic Tonsils', 'Palpable Mass'],
        'chest_lungs' => ['Symmetrical Chest Expansion', 'Retractions', 'Wheezes', 'Clear Breath Sounds', 'Crackles / Rales'],
        'heart' => ['Adynamic Precordium', 'Heaves / Thrills', 'Murmurs', 'Normal Rate Regular Rhythm'],
        'abdomen' => ['Flat', 'Flabby', 'Tenderness', 'Globular', 'Muscle Guarding', 'Palpable Mass'],
        'extremities' => ['Gross Deformity', 'Normal Gait', 'Full and Equal Pulses'],
    ];

    /** @return array<string, array{findings: array<int, string>, other: string}> */
    public static function emptyExamination(): array
    {
        return array_fill_keys(array_keys(self::EXAMINATIONS), ['findings' => [], 'other' => '']);
    }

    /** @param array<string, mixed> $details */
    public static function audit(int $patientId, ?int $queueId, string $action, array $details): void
    {
        DB::table('clinical_record_audits')->insert([
            'patient_id' => $patientId, 'queue_id' => $queueId, 'user_id' => auth()->id(),
            'action' => $action, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }
}
