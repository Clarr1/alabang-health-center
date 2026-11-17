<?php

namespace App\Livewire;

use App\Models\PatientInformation;
use App\Services\PatientCategories;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

trait PatientFormFields
{
    use RegistrationProfileFields {
        profileSections as protected registrationProfileSections;
    }

    public function hydrate(): void
    {
        Gate::authorize('patient-profile-manage');
        $this->synchronizePatientCategory();
    }

    public function updatedPatientCategory(): void
    {
        $this->synchronizePatientCategory();
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    #[Computed]
    public function profileSections(): array
    {
        return $this->patientCategory === null ? [] : $this->registrationProfileSections();
    }

    protected function synchronizePatientCategory(): void
    {
        $this->calculatePatientAge();
        $category = app(PatientCategories::class)->suggest($this->birthdate);
        if ($this->patientCategory !== $category) {
            $this->showReview = false;
        }
        $this->patientCategory = $category;
        $this->categoryOverrideReason = '';
        unset($this->profileSections, $this->suggestedCategory, $this->categoryMismatch);
        $allowedFields = array_keys(array_merge(...array_values($this->registrationProfileSections())));
        $this->profile = array_intersect_key($this->profile, array_flip($allowedFields));
    }

    protected function validateAutomaticPatientCategory(): void
    {
        app(PatientCategories::class)->validate($this->patientCategory, '', $this->birthdate, true);
    }

    public $first_name = '';

    public $middle_name = '';

    public $last_name = '';

    public $birthdate = '';

    #[Locked]
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
            $this->synchronizePatientCategory();
            $this->resetValidation(['birthdate', 'age', 'patientCategory', 'categoryOverrideReason']);
        }

        if (array_key_exists($property, $this->rules()) || str_contains($property, '.') || in_array($property, ['patientCategory', 'categoryOverrideReason', 'householdMode', 'householdId', 'householdRelationship', 'selectedConditions', 'otherCondition'], true)) {
            $this->showReview = false;
        }
    }

    public function reviewDetails(): void
    {
        Gate::authorize('queue-register');
        $this->validate();
        $this->validateAutomaticPatientCategory();
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
        $summary['age'] = $this->age;

        return $summary;
    }

    protected function calculatePatientAge(): void
    {
        $this->age = app(PatientCategories::class)->age($this->birthdate) ?? '';
    }

    protected function parsedPatientBirthdate(): ?DateTimeImmutable
    {
        return app(PatientCategories::class)->birthdate($this->birthdate);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString(), function (string $attribute, mixed $value, Closure $fail): void {
                if (app(PatientCategories::class)->age($value) > 150) {
                    $fail('The birthdate must be within the supported age range of 0 to 150 years.');
                }
            }],
            'sex' => ['required', 'in:Male,Female'],
            'address' => ['required', 'string'],
            'surveillance_residence' => ['required', 'in:Unverified,Alabang,Outside Scope'],
            'contact_number' => ['required', 'string', 'max:20', 'regex:/^[0-9+() .-]+$/'],
            'emergency_contact' => ['required', 'string', 'max:255'],
            'registration_date' => ['bail', 'required', 'date_format:Y-m-d', ...($this->parsedPatientBirthdate() !== null ? ['after_or_equal:birthdate'] : []), 'before_or_equal:'.now('Asia/Manila')->toDateString()],
        ];
    }
}
