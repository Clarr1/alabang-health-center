<?php

namespace App\Livewire;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

trait VitalSignFields
{
    public $blood_pressure = '';

    public $temperature = '';

    public $pulse_rate = '';

    public $respiratory_rate = '';

    public $height = '';

    public $weight = '';

    public $bmi = '';

    public $oxygen_saturation = '';

    public $waistline = '';

    public string $nutritional_status = '';

    public string $nutrition_assessment_method = '';

    public $recorded_at = '';

    /** @return array<string, array<int, mixed>> */
    protected function vitalSignRules(): array
    {
        return [
            'blood_pressure' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{1,3}\/[0-9]{1,3}$/D', function (string $attribute, mixed $value, \Closure $fail): void {
                if (preg_match('/^([0-9]+)\/([0-9]+)$/D', (string) $value, $matches) === 1 && ((int) $matches[1] < 1 || (int) $matches[1] > 300 || (int) $matches[2] < 1 || (int) $matches[2] > 200 || (int) $matches[2] >= (int) $matches[1])) {
                    $fail('Enter a valid systolic/diastolic pressure in mmHg.');
                }
            }],
            'temperature' => ['nullable', 'numeric', 'min:25', 'max:45'],
            'pulse_rate' => ['nullable', 'integer', 'min:0', 'max:220'],
            'respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:80'],
            'height' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'weight' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'bmi' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'oxygen_saturation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'waistline' => ['nullable', 'numeric', 'gt:0', 'max:300'],
            'nutritional_status' => ['nullable', 'string', 'max:255', 'required_with:nutrition_assessment_method'],
            'nutrition_assessment_method' => ['nullable', 'string', 'required_with:nutritional_status', Rule::in($this->nutritionAssessmentMethods())],
            'recorded_at' => ['required', 'date'],
        ];
    }

    /** @return array<string, mixed> */
    protected function vitalSignValues(): array
    {
        return [
            'blood_pressure' => filled($this->blood_pressure) ? $this->blood_pressure : null,
            'temperature' => filled($this->temperature) ? $this->temperature : null,
            'pulse_rate' => filled($this->pulse_rate) ? (int) $this->pulse_rate : null,
            'respiratory_rate' => filled($this->respiratory_rate) ? (int) $this->respiratory_rate : null,
            'height' => filled($this->height) ? $this->height : null,
            'weight' => filled($this->weight) ? $this->weight : null,
            'bmi' => filled($this->bmi) ? $this->bmi : null,
            'oxygen_saturation' => filled($this->oxygen_saturation) ? $this->oxygen_saturation : null,
            'waistline' => filled($this->waistline) ? $this->waistline : null,
            'nutritional_status' => $this->nutritional_status ?: null,
            'nutrition_assessment_method' => $this->nutrition_assessment_method ?: null,
        ];
    }

    public function updatedHeight(): void
    {
        $this->calculateBmi();
    }

    public function updatedWeight(): void
    {
        $this->calculateBmi();
    }

    public function getBmiCategoryProperty(): string
    {
        if (! $this->isAdultNutritionAssessment()) {
            return 'Age-specific assessment required';
        }

        if ($this->bmi === '' || $this->bmi === null) {
            return '---';
        }

        $bmi = (float) $this->bmi;

        if ($bmi < 18.5) {
            return 'Underweight';
        }

        if ($bmi < 25) {
            return 'Normal';
        }

        if ($bmi < 30) {
            return 'Overweight';
        }

        return 'Obese';
    }

    protected function isAdultNutritionAssessment(): bool
    {
        $birthdate = $this instanceof PatientVitalSignForm ? $this->birthdate : $this->patient?->birthdate;

        return is_string($birthdate) && Carbon::canBeCreatedFromFormat($birthdate, 'Y-m-d') && Carbon::parse($birthdate)->age >= 20;
    }

    /** @return array<int, string> */
    public function nutritionAssessmentMethods(): array
    {
        return $this->isAdultNutritionAssessment()
            ? ['Adult BMI', 'Clinical nutrition assessment']
            : ['Age-specific growth assessment', 'Clinical nutrition assessment'];
    }

    protected function calculateBmi(): void
    {
        if (is_numeric($this->height) && is_numeric($this->weight) && (float) $this->height > 0 && (float) $this->weight > 0) {
            $heightInMeters = (float) $this->height / 100;
            $this->bmi = round((float) $this->weight / ($heightInMeters * $heightInMeters), 1);

            return;
        }

        $this->bmi = '';
    }
}
