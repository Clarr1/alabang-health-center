<?php

namespace App\Services;

use App\Models\Household;
use App\Models\PatientInformation;
use App\Models\PatientProfileAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatientRegistration
{
    /** @return array<string, array<string, array<string, mixed>>> */
    public function sections(string $birthdate, bool $includeHistorical = false, ?string $category = null): array
    {
        $category = app(PatientCategories::class)->suggest($birthdate);
        $sections = [];
        foreach (PatientRegistrationSchema::sections() as $section => $fields) {
            if (PatientRegistrationSchema::sensitive($section) && Gate::denies('patient-sensitive')) {
                continue;
            }
            if ($includeHistorical) {
                $sections[$section] = $fields;

                continue;
            }
            if (in_array($section, ['Child Health Profile (0–9 years)', 'Immunization Child Information'], true) && $category !== 'Child') {
                continue;
            }
            if ($section === 'Adolescent HEADSS Assessment' && $category !== 'Adolescent') {
                continue;
            }
            if ($section === 'Adolescent and Adult Health Profile') {
                if (! in_array($category, ['Adolescent', 'Adult'], true)) {
                    continue;
                }
                $section = $category.' Health Profile';
            }
            if ($section === 'Women of Reproductive Age' && ! in_array($category, ['Adolescent', 'Adult'], true)) {
                continue;
            }
            $sections[$section] = $fields;
        }

        return $sections;
    }

    /** @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public function fieldRules(string $prefix, array $fields, array $values): array
    {
        $rules = [];
        foreach ($fields as $key => $field) {
            if (! PatientRegistrationSchema::visible($key, $fields, $values)) {
                $rules[$prefix.'.'.$key] = ['exclude'];

                continue;
            }
            $rules[$prefix.'.'.$key] = match ($field['type']) {
                'select' => ['nullable', Rule::in($field['options'])],
                'number' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'date' => ['nullable', 'date_format:Y-m-d', ...($key !== 'edc' ? ['before_or_equal:'.now('Asia/Manila')->toDateString()] : [])],
                'email' => ['nullable', 'email', 'max:255'],
                'tel' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
                default => ['nullable', 'string', $field['type'] === 'textarea' ? 'max:10000' : 'max:255'],
            };
            if (in_array($key, ['gravida', 'para', 'family_count'], true)) {
                $rules[$prefix.'.'.$key] = ['nullable', 'integer', $key === 'family_count' ? 'min:1' : 'min:0', 'max:1000'];
            }
            if ($key === 'residency_years') {
                $rules[$prefix.'.'.$key] = ['nullable', 'numeric', 'min:0', 'max:150'];
            }
            if ($key === 'edc' && filled($values['lmp'] ?? null)) {
                $rules[$prefix.'.'.$key][] = 'after_or_equal:'.$prefix.'.lmp';
            }
            if ($key === 'para' && filled($values['gravida'] ?? null)) {
                $rules[$prefix.'.'.$key][] = 'lte:'.$prefix.'.gravida';
            }
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function validate(array $input, string $birthdate): array
    {
        Gate::authorize('patient-profile-manage');
        $category = $input['patientCategory'] ?? null;
        app(PatientCategories::class)->validate($category, $input['categoryOverrideReason'] ?? '', $birthdate);
        $sections = $this->sections($birthdate, false, $category);
        $fields = array_merge(...array_values($sections));
        $rules = [
            'patientCategory' => ['nullable', Rule::in(array_keys(app(PatientCategories::class)->choices()))],
            'categoryOverrideReason' => ['nullable', 'string', 'max:1000'],
            'profile' => ['array:'.implode(',', array_keys($fields))],
            'householdMode' => ['required', Rule::in(['none', 'new', 'existing'])],
            'householdId' => [Rule::requiredIf(($input['householdMode'] ?? '') === 'existing'), 'nullable', 'integer', 'exists:households,id'],
            'householdRelationship' => ['nullable', 'string', 'max:255'],
            'transferReason' => ['nullable', 'string', 'max:1000'],
            'updateHousehold' => ['boolean'],
            'selectedConditions' => ['array', 'max:5'],
            'selectedConditions.*' => [Rule::in(PatientRegistrationSchema::CONDITIONS), 'distinct'],
            'otherCondition' => ['nullable', 'string', 'max:255'],
            'newDoses' => ['array', 'max:30'],
            'newDoses.*' => ['array:vaccine_name,other_vaccine,schedule,other_schedule,dose,status,administered_at,administered_by,remarks,supersedes_id'],
            'newDoses.*.vaccine_name' => ['required', Rule::in(PatientRegistrationSchema::VACCINES)],
            'newDoses.*.other_vaccine' => ['required_if:newDoses.*.vaccine_name,Others', 'nullable', 'string', 'max:255'],
            'newDoses.*.schedule' => ['required', Rule::in(PatientRegistrationSchema::SCHEDULES)],
            'newDoses.*.other_schedule' => ['required_if:newDoses.*.schedule,Other', 'nullable', 'string', 'max:255'],
            'newDoses.*.dose' => ['required', 'string', 'max:100'],
            'newDoses.*.status' => ['required', Rule::in(['Completed', 'Pending', 'Missed'])],
            'newDoses.*.administered_at' => ['required_if:newDoses.*.status,Completed', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$birthdate, 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'newDoses.*.administered_by' => ['nullable', 'string', 'max:255'],
            'newDoses.*.remarks' => ['nullable', 'string', 'max:10000'],
            'newDoses.*.supersedes_id' => ['nullable', 'integer'],
            ...$this->fieldRules('profile', $fields, $input['profile'] ?? []),
        ];
        if (($input['householdMode'] ?? '') === 'new' || ($input['updateHousehold'] ?? false)) {
            Gate::authorize('queue-register');
            $rules = [...$rules, ...$this->fieldRules('householdData', PatientRegistrationSchema::householdFields(), $input['householdData'] ?? [])];
            $rules['householdData.label'] = ['required', 'string', 'max:255'];
            $rules['householdData.address'] = ['required', 'string', 'max:10000'];
        }
        $validated = Validator::make($input, $rules)->validate();
        $dateRules = [];
        foreach ($fields as $key => $field) {
            if ($field['type'] === 'date' && filled($validated['profile'][$key] ?? null)) {
                $dateRules['profile.'.$key] = ['after_or_equal:'.$birthdate];
            }
        }
        Validator::make($validated, $dateRules)->validate();
        foreach ($validated['newDoses'] ?? [] as $index => $dose) {
            if (($dose['status'] ?? '') !== 'Completed' && filled($dose['administered_at'] ?? null)) {
                throw ValidationException::withMessages(['newDoses.'.$index.'.administered_at' => 'A recorded administration must have Completed status.']);
            }
        }

        return $validated;
    }

    public function save(PatientInformation $patient, array $input, int $profileVersion, int $householdVersion): void
    {
        Gate::authorize('patient-profile-manage');
        DB::transaction(function () use ($patient, $input, $profileVersion, $householdVersion): void {
            $patient = PatientInformation::lockForUpdate()->findOrFail($patient->patient_id);
            $data = $this->validate($input, $patient->birthdate);
            $category = $data['patientCategory'] ?? null;
            $reason = trim($data['categoryOverrideReason'] ?? '');
            app(PatientCategories::class)->validate($category, $reason, $patient->birthdate, $patient->patient_category !== null);
            $profile = $patient->registrationProfile()->first();
            if (($profile?->version ?? 0) !== $profileVersion) {
                throw ValidationException::withMessages(['profile' => 'This profile changed. Reload the patient before saving.']);
            }
            $oldProfile = $profile?->getAttributes() ?? [];
            $profile ??= $patient->registrationProfile()->make();
            $fields = array_merge(...array_values($this->sections($patient->birthdate, false, $category)));
            $values = $data['profile'] ?? [];
            foreach ($fields as $key => $field) {
                $profile->{$key} = PatientRegistrationSchema::visible($key, $fields, $values) ? (($values[$key] ?? '') === '' ? null : $values[$key]) : null;
            }
            $profile->prepared_by ??= auth()->id();
            $profile->completed_at ??= now();
            $profile->version = $profileVersion + 1;
            $profile->save();
            $this->audit($patient, 'profile-updated', ['before' => $oldProfile, 'after' => $profile->getAttributes()]);

            if (Gate::allows('queue-register')) {
                $categories = app(PatientCategories::class);
                $mismatches = $categories->mismatches($category, $patient->birthdate);
                if ($mismatches) {
                    Gate::authorize('patient-category-override');
                }
                $reason = $mismatches ? $reason : null;
                if ($patient->patient_category !== $category || $patient->category_override_reason !== $reason) {
                    $this->audit($patient, 'patient-category-changed', ['from' => $patient->patient_category, 'to' => $category, 'suggested' => $categories->suggest($patient->birthdate), 'age' => $categories->age($patient->birthdate), 'reason' => $reason]);
                    $patient->update(['patient_category' => $category, 'category_override_reason' => $reason]);
                }
                $this->saveHousehold($patient, $data, $householdVersion);
            } elseif ($category !== $patient->current_category || filled($data['categoryOverrideReason'] ?? '') || ($data['householdId'] ?? null) != $patient->household_id || ($data['householdMode'] === 'none' && $patient->household_id !== null) || $data['householdMode'] === 'new' || ($data['householdRelationship'] ?? null) != $patient->household_relationship) {
                abort(403);
            }
            $names = $data['selectedConditions'] ?? [];
            if (filled($data['otherCondition'] ?? null)) {
                $names[] = trim($data['otherCondition']);
            }
            $names = array_values(array_unique($names));
            $active = $patient->conditions()->whereNull('ended_at')->get();
            foreach ($active as $condition) {
                if (! in_array($condition->name, $names, true)) {
                    $condition->update(['ended_at' => now()]);
                }
            }
            foreach (array_diff($names, $active->pluck('name')->all()) as $name) {
                $patient->conditions()->create(['name' => $name, 'status' => 'Reported', 'recorded_by' => auth()->id()]);
            }
            foreach ($data['newDoses'] ?? [] as $dose) {
                if (filled($dose['supersedes_id'] ?? null) && ! $patient->immunizationDoses()->whereKey($dose['supersedes_id'])->exists()) {
                    throw ValidationException::withMessages(['newDoses' => 'The original dose must belong to this patient.']);
                }
                $dose['vaccine_name'] = $dose['vaccine_name'] === 'Others' ? $dose['other_vaccine'] : $dose['vaccine_name'];
                $dose['schedule'] = $dose['schedule'] === 'Other' ? $dose['other_schedule'] : $dose['schedule'];
                unset($dose['other_vaccine'], $dose['other_schedule']);
                $dose['administered_at'] = filled($dose['administered_at'] ?? null) ? $dose['administered_at'] : null;
                $saved = $patient->immunizationDoses()->create([...$dose, 'recorded_by' => auth()->id()]);
                $this->audit($patient, 'immunization-added', ['dose' => $saved->getAttributes()]);
            }
            $this->audit($patient, 'conditions-updated', ['before' => $active->toArray(), 'after' => $names]);
        }, 5);
    }

    private function saveHousehold(PatientInformation $patient, array $data, int $version): void
    {
        $oldId = $patient->household_id;
        $household = null;
        if ($data['householdMode'] === 'new') {
            $household = Household::create(['family_number' => 'FN-'.Str::ulid(), ...$this->householdValues($data['householdData'])]);
            $household->update(['family_number' => 'FN-'.str_pad((string) $household->id, 4, '0', STR_PAD_LEFT)]);
            $this->audit($patient, 'household-created', ['after' => $household->getAttributes()], $household->id);
        } elseif ($data['householdMode'] === 'existing') {
            $household = Household::lockForUpdate()->findOrFail($data['householdId']);
            if ($data['updateHousehold'] ?? false) {
                if ($household->version !== $version) {
                    throw ValidationException::withMessages(['householdData' => 'This household changed. Select it again before updating.']);
                }
                $before = $household->getAttributes();
                $household->update([...$this->householdValues($data['householdData']), 'version' => $version + 1]);
                $this->audit($patient, 'household-updated', ['before' => $before, 'after' => $household->getAttributes()], $household->id);
            }
        }
        if ($oldId !== null && $oldId !== $household?->id && ! filled($data['transferReason'] ?? null)) {
            throw ValidationException::withMessages(['transferReason' => 'Provide a reason to transfer or remove household membership.']);
        }
        $beforeRelationship = $patient->household_relationship;
        $patient->update(['household_id' => $household?->id, 'household_relationship' => $household === null ? null : ($data['householdRelationship'] ?? null)]);
        if ($oldId !== $patient->household_id || $beforeRelationship !== $patient->household_relationship) {
            $this->audit($patient, 'household-membership-changed', ['from' => $oldId, 'to' => $patient->household_id, 'relationship' => $patient->household_relationship, 'reason' => $data['transferReason'] ?? null], $patient->household_id);
        }
    }

    /** @return array<string, mixed> */
    private function householdValues(array $values): array
    {
        $result = [];
        $fields = PatientRegistrationSchema::householdFields();
        foreach ($fields as $key => $field) {
            $result[$key] = PatientRegistrationSchema::visible($key, $fields, $values) && filled($values[$key] ?? null) ? $values[$key] : null;
        }

        return $result;
    }

    public function audit(PatientInformation $patient, string $action, array $details = [], ?int $householdId = null): void
    {
        PatientProfileAudit::create(['patient_id' => $patient->patient_id, 'household_id' => $householdId, 'user_id' => auth()->id(), 'action' => $action, 'details' => ['patient_id' => $patient->patient_id, ...$details]]);
    }
}
