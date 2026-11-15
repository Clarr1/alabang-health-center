<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;

trait PatientFormFields
{
    use RegistrationProfileFields;

    public $first_name = '';

    public $middle_name = '';

    public $last_name = '';

    public $birthdate = '';

    public $age = '';

    public $sex = 'Male';

    public $address = '';

    public string $surveillance_residence = 'Unverified';

    public $contact_number = '';

    public $emergency_contact = '';

    public $registration_date = '';

    public bool $showReview = false;

    public function updated(string $property): void
    {
        if ($property === 'birthdate') {
            $this->calculatePatientAge();
            unset($this->profileSections, $this->suggestedCategory, $this->categoryMismatch);
            $allowedFields = array_keys(array_merge(...array_values($this->profileSections())));
            $this->profile = array_intersect_key($this->profile, array_flip($allowedFields));
        }

        if (array_key_exists($property, $this->rules()) || str_contains($property, '.') || in_array($property, ['patientCategory', 'categoryOverrideReason', 'householdMode', 'householdId', 'householdRelationship', 'selectedConditions', 'otherCondition'], true)) {
            $this->showReview = false;
        }
    }

    public function reviewDetails(): void
    {
        Gate::authorize('queue-register');
        $this->validate();
        $this->validateRegistrationProfile();
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

        $birthdate = $this->parsedPatientBirthdate();

        if (! filled($this->first_name) || ! filled($this->last_name)
            || $birthdate === null) {
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
        $birthdate = $this->parsedPatientBirthdate();

        $this->age = $birthdate !== null && $birthdate <= $today
            ? $birthdate->diff($today)->y
            : '';
    }

    protected function parsedPatientBirthdate(): ?DateTimeImmutable
    {
        if (! is_string($this->birthdate) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $this->birthdate) !== 1) {
            return null;
        }

        $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', $this->birthdate, now('Asia/Manila')->getTimezone());

        return $birthdate !== false && $birthdate->format('Y-m-d') === $this->birthdate ? $birthdate : null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'age' => ['required', 'integer', 'min:0', 'max:150', function (string $attribute, mixed $value, Closure $fail): void {
                $today = now('Asia/Manila')->startOfDay();
                $birthdate = $this->parsedPatientBirthdate();
                if ($birthdate !== null && $birthdate <= $today
                    && (int) $value !== $birthdate->diff($today)->y) {
                    $fail('The age must match the patient birthdate.');
                }
            }],
            'sex' => ['required', 'in:Male,Female'],
            'address' => ['required', 'string'],
            'surveillance_residence' => ['required', 'in:Unverified,Alabang,Outside Scope'],
            'contact_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+() .-]+$/'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'registration_date' => ['bail', 'required', 'date_format:Y-m-d', ...($this->parsedPatientBirthdate() !== null ? ['after_or_equal:birthdate'] : []), 'before_or_equal:'.now('Asia/Manila')->toDateString()],
        ];
    }
}
