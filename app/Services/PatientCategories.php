<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PatientCategories
{
    /** @return array<string, array{min_age: int, max_age: int, description: string}> */
    public function choices(): array
    {
        return config('patient-registration.categories');
    }

    public function age(mixed $birthdate): ?int
    {
        if (! is_string($birthdate) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $birthdate) !== 1) {
            return null;
        }
        $today = now('Asia/Manila')->startOfDay();
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate, $today->getTimezone());
        if ($parsed === false || $parsed->format('Y-m-d') !== $birthdate || $parsed > $today) {
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
        $reason = trim($reason);
        $mismatches = $this->mismatches($category, $birthdate);
        Validator::make(['patientCategory' => $category, 'categoryOverrideReason' => $reason], [
            'patientCategory' => [$required ? 'required' : 'nullable', Rule::in(array_keys($this->choices()))],
            'categoryOverrideReason' => [Rule::requiredIf($mismatches), 'nullable', 'string', ...($mismatches ? ['min:10', 'not_regex:/^\s*$/u'] : []), 'max:1000'],
        ], ['categoryOverrideReason.required' => 'Explain why this category is appropriate despite the calculated age.'])->validate();
    }
}
