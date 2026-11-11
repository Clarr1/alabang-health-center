<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\SurveillanceClassification;
use App\Models\SyndromeCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SyndromeClassifier
{
    public function encounterTime(MedicalRecord $record): ?CarbonImmutable
    {
        $time = $record->encounter_at ? CarbonImmutable::instance($record->encounter_at)->utc() : ($record->created_at ? CarbonImmutable::instance($record->created_at)->utc() : null);
        if ($time === null || $record->consultation_date === null || $time->setTimezone('Asia/Manila')->toDateString() !== $record->consultation_date->toDateString()) {
            return null;
        }

        return $time;
    }

    public function isValid(MedicalRecord $record): bool
    {
        return filled($record->diagnosis) && $this->encounterTime($record) !== null
            && $record->vitalSign !== null && (int) $record->vitalSign->patient_id === (int) $record->patient_id
            && ($record->queue_id === null || ($record->queue !== null && $record->queue->status === 'Completed'
                && (int) $record->queue->patient_id === (int) $record->patient_id && (int) $record->vitalSign->queue_id === (int) $record->queue_id));
    }

    /** @return Collection<int, SurveillanceClassification> */
    public function classify(MedicalRecord $record): Collection
    {
        $record->loadMissing('symptoms', 'queue', 'vitalSign');
        if (! $this->isValid($record)) {
            return collect();
        }
        $occurredAt = $this->encounterTime($record);
        if ($occurredAt->isFuture() || $record->symptoms->isEmpty()) {
            return collect();
        }
        $classifications = collect();
        foreach (SyndromeCategory::where('is_active', true)->get() as $category) {
            $existing = SurveillanceClassification::where('medical_record_id', $record->getKey())->where('syndrome_category_id', $category->id)->first();
            if ($existing) {
                $classifications->push($existing);

                continue;
            }
            $rule = $category->rules()->whereNotNull('approved_at')->where('approved_at', '<=', now())
                ->where('applies_from', '<=', $occurredAt)->latest('version')->with('symptoms')->first();
            if (! $rule) {
                continue;
            }
            $matched = $record->symptoms->modelKeys();
            $required = $rule->symptoms->modelKeys();
            $intersection = array_values(array_intersect($matched, $required));
            $classifications->push(SurveillanceClassification::firstOrCreate([
                'medical_record_id' => $record->getKey(), 'syndrome_category_id' => $category->id,
            ], [
                'syndrome_rule_version_id' => $rule->id, 'occurred_at' => $occurredAt,
                'matches' => count($intersection) >= $rule->minimum_matches,
                'evidence' => [
                    'syndrome_name' => $category->name, 'rule_version' => $rule->version,
                    'minimum_matches' => $rule->minimum_matches, 'mapped_symptom_ids' => $required,
                    'matched_symptom_ids' => $intersection,
                    'structured_symptoms' => $record->symptoms->mapWithKeys(fn ($symptom): array => [$symptom->id => $symptom->pivot->symptom_name])->all(),
                    'approved_by' => $rule->approved_by, 'approved_at' => $rule->approved_at->toIso8601String(),
                    'residence_at_classification' => $record->patient?->surveillance_residence,
                ],
            ]));
        }

        return $classifications;
    }
}
