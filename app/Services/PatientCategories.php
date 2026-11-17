<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PatientCategories
{
    /** @return array<string, array{min_age: int, max_age: int, description: string}> */
    public function choices(): array
    {
        return config('patient-registration.categories');
    }

    public function birthdate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, now('Asia/Manila')->getTimezone());

        return $parsed !== false && $parsed->format('Y-m-d') === $value ? $parsed : null;
    }

    public function age(mixed $birthdate, ?DateTimeInterface $asOf = null): ?int
    {
        $today = $asOf === null ? now('Asia/Manila')->startOfDay()
            : DateTimeImmutable::createFromInterface($asOf)->setTimezone(now('Asia/Manila')->getTimezone())->setTime(0, 0);
        $parsed = $this->birthdate($birthdate);
        if ($parsed === null || $parsed > $today) {
            return null;
        }

        return $parsed->diff($today)->y;
    }

    public function suggest(mixed $birthdate): ?string
    {
        $age = $this->age($birthdate);
        if ($age === null) {
            return null;
        }
        foreach ($this->choices() as $category => $range) {
            if ($age >= $range['min_age'] && $age <= $range['max_age']) {
                return $category;
            }
        }

        return null;
    }

    public function mismatches(?string $category, mixed $birthdate): bool
    {
        return $category !== null && $this->age($birthdate) !== null && $this->suggest($birthdate) !== $category;
    }

    public function validate(?string $category, string $reason, mixed $birthdate, bool $required = false): void
    {
        Validator::make(['patientCategory' => $category, 'categoryOverrideReason' => $reason], [
            'patientCategory' => [$required ? 'required' : 'nullable', Rule::in(array_keys($this->choices())), Rule::in([$this->suggest($birthdate)])],
            'categoryOverrideReason' => ['prohibited'],
        ], ['patientCategory.in' => 'The category must match the patient’s current age.'])->validate();
    }
}
