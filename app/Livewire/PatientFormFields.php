<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;

trait PatientFormFields
{
    public $first_name = '';

    public $middle_name = '';

    public $last_name = '';

    public $birthdate = '';

    public $age = '';

    public $sex = 'Male';

    public $address = '';

    public $contact_number = '';

    public $emergency_contact = '';

    public $registration_date = '';

    public bool $showReview = false;

    public function updated(string $property): void
    {
        if ($property === 'birthdate') {
            $this->calculatePatientAge();
        }

        if (array_key_exists($property, $this->rules())) {
            $this->showReview = false;
        }
    }

    public function reviewDetails(): void
    {
        Gate::authorize('queue-register');
        $this->validate();
        $this->showReview = true;
    }

    public function returnToDetails(): void
    {
        $this->showReview = false;
    }

    /** @return Collection<int, PatientInformation> */
    #[Computed]
    public function duplicatePatients(): Collection
    {
        Gate::authorize('queue-register');

        if (! is_string($this->birthdate) || ! is_string($this->first_name) || ! is_string($this->last_name)) {
            return collect();
        }

        $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->birthdate);

        if (! filled($this->first_name) || ! filled($this->last_name)
            || $birthdate === false || $birthdate->format('Y-m-d') !== $this->birthdate) {
            return collect();
        }

        $excludedPatientId = property_exists($this, 'patientId') ? $this->patientId : null;

        return PatientInformation::query()
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [mb_strtolower(trim($this->first_name))])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [mb_strtolower(trim($this->last_name))])
            ->whereDate('birthdate', $this->birthdate)
            ->when($excludedPatientId !== null, fn (Builder $query): Builder => $query->where('patient_id', '!=', $excludedPatientId))
            ->orderBy('patient_id')->limit(5)->get();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function patientSummary(): array
    {
        $summary = [];

        foreach (array_keys($this->rules()) as $field) {
            $summary[$field] = $this->{$field};
        }

        return $summary;
    }

    protected function calculatePatientAge(): void
    {
        $today = now('Asia/Manila')->startOfDay();
        $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->birthdate, $today->getTimezone());

        $this->age = $birthdate !== false && $birthdate->format('Y-m-d') === $this->birthdate && $birthdate <= $today
            ? $birthdate->diff($today)->y
            : '';
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date'],
            'age' => ['required', 'integer', 'min:0', 'max:150'],
            'sex' => ['required', 'in:Male,Female'],
            'address' => ['required', 'string'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'registration_date' => ['required', 'date'],
        ];
    }
}
