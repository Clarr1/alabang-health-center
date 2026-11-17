<?php

namespace App\Livewire;

use App\Models\Household;
use App\Models\PatientInformation;
use App\Services\PatientCategories;
use App\Services\PatientRegistration;
use App\Services\PatientRegistrationSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

trait RegistrationProfileFields
{
    public function hydrate(): void
    {
        Gate::authorize('patient-profile-manage');
        $allowed = array_keys(array_merge(...array_values($this->profileSections())));
        $this->profile = array_intersect_key($this->profile, array_flip($allowed));
    }

    public function updatedProfile(mixed $value, ?string $key = null): void
    {
        unset($this->profileSections);
        if ($key === 'reproductive_applicable') {
            $this->pruneRegistrationProfile();
        }
        if (Gate::allows('patient-sensitive')) {
            return;
        }
        foreach (PatientRegistrationSchema::sections() as $section => $fields) {
            if (PatientRegistrationSchema::sensitive($section)
                && ($key !== null ? array_key_exists($key, $fields) : is_array($value) && array_intersect_key($value, $fields) !== [])) {
                Gate::authorize('patient-sensitive');
            }
        }
    }

    public array $profile = [];

    public ?string $patientCategory = null;

    public string $categoryOverrideReason = '';

    #[Locked]
    public ?string $originalPatientCategory = null;

    public function updatingPatientCategory(): void
    {
        abort(403, 'The patient category is calculated from the birthdate.');
    }

    public function updatingCategoryOverrideReason(): void
    {
        abort(403, 'The patient category cannot be overridden.');
    }

    protected function pruneRegistrationProfile(): void
    {
        $allowed = array_keys(array_merge(...array_values($this->profileSections())));
        $this->profile = array_intersect_key($this->profile, array_flip($allowed));
    }

    #[Computed]
    public function suggestedCategory(): ?string
    {
        return app(PatientCategories::class)->suggest($this->birthdate);
    }

    #[Computed]
    public function categoryMismatch(): bool
    {
        return app(PatientCategories::class)->mismatches($this->patientCategory, $this->birthdate);
    }

    public string $householdMode = 'none';

    public ?int $householdId = null;

    public string $householdSearch = '';

    public array $householdData = [];

    public ?string $householdRelationship = null;

    public string $transferReason = '';

    public bool $updateHousehold = false;

    public array $selectedConditions = [];

    public string $otherCondition = '';

    public array $newDoses = [];

    /** @var array<int, string> */
    #[Locked]
    public array $newDoseKeys = [];

    #[Locked]
    public int $profileVersion = 0;

    #[Locked]
    public int $householdVersion = 0;

    #[Locked]
    public ?int $originalHouseholdId = null;

    /** @return array<string, array<string, array<string, mixed>>> */
    #[Computed]
    public function profileSections(): array
    {
        Gate::authorize('patient-profile-manage');

        return app(PatientRegistration::class)->sections(is_string($this->birthdate) ? $this->birthdate : '', false, $this->patientCategory);
    }

    /** @return Collection<int, Household> */
    #[Computed]
    public function matchingHouseholds(): Collection
    {
        Gate::authorize('queue-register');

        return Household::query()->when(filled($this->householdSearch), function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->where('family_number', 'like', '%'.$this->householdSearch.'%')->orWhere('label', 'like', '%'.$this->householdSearch.'%');
            });
        })->orderBy('label')->limit(15)->get();
    }

    public function selectHousehold(int $id): void
    {
        Gate::authorize('queue-register');
        $household = Household::findOrFail($id);
        $this->householdMode = 'existing';
        $this->householdId = $household->id;
        $this->householdVersion = $household->version;
        $this->householdData = $household->only(array_keys(PatientRegistrationSchema::householdFields()));
        $this->updateHousehold = false;
        $this->showReview = false;
    }

    public function addDose(): void
    {
        Gate::authorize('patient-profile-manage');
        if (count($this->newDoses) < 30) {
            $this->newDoseKeys[] = (string) Str::uuid();
            $this->newDoses[] = ['vaccine_name' => '', 'other_vaccine' => '', 'schedule' => '', 'other_schedule' => '', 'dose' => '', 'status' => 'Pending', 'administered_at' => '', 'administered_by' => '', 'remarks' => '', 'supersedes_id' => null];
        }
        $this->showReview = false;
    }

    public function removeDose(int $index): void
    {
        Gate::authorize('patient-profile-manage');
        unset($this->newDoses[$index], $this->newDoseKeys[$index]);
        $this->newDoseKeys = array_values($this->newDoseKeys);
        $this->newDoses = array_values($this->newDoses);
        $this->showReview = false;
    }

    protected function initializeRegistrationProfile(PatientInformation $patient): void
    {
        Gate::authorize('patient-profile-manage');
        $this->originalPatientCategory = $patient->patient_category;
        $this->patientCategory = $this->categoryForPatient($patient);
        $this->categoryOverrideReason = $patient->category_override_reason ?? '';
        unset($this->profileSections);
        $this->profile = [];
        if (Gate::allows('patient-sensitive') && $patient->registrationProfile()->value('reproductive_applicable') === 'Yes') {
            $this->profile['reproductive_applicable'] = 'Yes';
        }
        $columns = array_keys(array_merge(...array_values($this->profileSections())));
        $saved = $patient->registrationProfile()->select(['id', 'patient_id', 'version', ...$columns])->first();
        $this->profile = $saved?->only($columns) ?? [];
        $this->profileVersion = $saved?->version ?? 0;
        $this->originalHouseholdId = $patient->household_id;
        $this->householdId = $patient->household_id;
        $this->householdMode = $patient->household_id === null ? 'none' : 'existing';
        $this->householdRelationship = $patient->household_relationship;
        $household = $patient->household;
        $this->householdData = $household?->only(array_keys(PatientRegistrationSchema::householdFields())) ?? [];
        $this->householdVersion = $household?->version ?? 0;
        $conditions = $patient->conditions()->whereNull('ended_at')->pluck('name')->all();
        $this->selectedConditions = array_values(array_intersect($conditions, PatientRegistrationSchema::CONDITIONS));
        $this->otherCondition = implode('; ', array_diff($conditions, PatientRegistrationSchema::CONDITIONS));
        $this->newDoses = [];
        $this->newDoseKeys = [];
        $this->transferReason = '';
        $this->updateHousehold = false;
        if (Gate::allows('patient-sensitive')) {
            app(PatientRegistration::class)->audit($patient, 'sensitive-profile-accessed');
        }
    }

    protected function registrationProfileInput(): array
    {
        return $this->only(['patientCategory', 'categoryOverrideReason', 'profile', 'householdMode', 'householdId', 'householdData', 'householdRelationship', 'transferReason', 'updateHousehold', 'selectedConditions', 'otherCondition', 'newDoses']);
    }

    protected function categoryForPatient(PatientInformation $patient): ?string
    {
        return $patient->patient_category ?? app(PatientCategories::class)->suggest($patient->birthdate);
    }

    protected function validateRegistrationProfile(bool $includeDoses = true): void
    {
        $input = $this->registrationProfileInput();
        if (! $includeDoses) {
            $input['newDoses'] = [];
        }
        app(PatientRegistration::class)->validate($input, is_string($this->birthdate) ? $this->birthdate : '');
        if ($this->originalHouseholdId !== null
            && ($this->householdMode !== 'existing' || $this->householdId !== $this->originalHouseholdId)
            && ! filled($this->transferReason)) {
            throw ValidationException::withMessages(['transferReason' => 'Provide a reason to transfer or remove household membership.']);
        }
    }

    protected function saveRegistrationProfile(PatientInformation $patient): void
    {
        app(PatientRegistration::class)->save($patient, $this->registrationProfileInput(), $this->profileVersion, $this->householdVersion);
    }

    protected function resetRegistrationProfile(): void
    {
        $this->reset(['patientCategory', 'categoryOverrideReason', 'originalPatientCategory', 'profile', 'householdMode', 'householdId', 'householdSearch', 'householdData', 'householdRelationship', 'transferReason', 'updateHousehold', 'selectedConditions', 'otherCondition', 'newDoses', 'newDoseKeys', 'profileVersion', 'householdVersion', 'originalHouseholdId']);
    }
}
