<?php

namespace App\Livewire;

trait VitalSignFields
{
    public $blood_pressure = '';

    public $temperature = '';

    public $pulse_rate = '';

    public $respiratory_rate = '';

    public $height = '';

    public $weight = '';

    public $bmi = '';

    public $recorded_at = '';

    /** @return array<string, array<int, string>> */
    protected function vitalSignRules(): array
    {
        return [
            'blood_pressure' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+\/[0-9]+$/D'],
            'temperature' => ['nullable', 'numeric', 'min:25', 'max:45'],
            'pulse_rate' => ['nullable', 'integer', 'min:0', 'max:220'],
            'respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:80'],
            'height' => ['nullable', 'numeric', 'min:20', 'max:300'],
            'weight' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'bmi' => ['nullable', 'numeric', 'min:5', 'max:100'],
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

    protected function calculateBmi(): void
    {
        if (is_numeric($this->height) && is_numeric($this->weight) && (float) $this->height > 0) {
            $heightInMeters = (float) $this->height / 100;
            $this->bmi = round((float) $this->weight / ($heightInMeters * $heightInMeters), 1);

            return;
        }

        $this->bmi = '';
    }
}
