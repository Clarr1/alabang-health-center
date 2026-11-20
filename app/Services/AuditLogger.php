<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Diagnosis;
use App\Models\FollowUp;
use App\Models\Household;
use App\Models\ImmunizationDose;
use App\Models\MedicalRecord;
use App\Models\PatientCondition;
use App\Models\PatientInformation;
use App\Models\PatientProfile;
use App\Models\Queue;
use App\Models\SurveillanceAlert;
use App\Models\SurveillanceAlertReview;
use App\Models\SurveillanceSetting;
use App\Models\SyndromeCategory;
use App\Models\SyndromeRuleVersion;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /** @var array<int, string> */
    private const IGNORED_FIELDS = [
        'created_at',
        'updated_at',
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'api_token',
        'access_token',
        'secret',
    ];

    /** @var array<class-string<Model>, string> */
    private const MODULES = [
        PatientInformation::class => 'Patient Management',
        PatientProfile::class => 'Patient Management',
        Household::class => 'Family Records',
        PatientCondition::class => 'Patient Management',
        ImmunizationDose::class => 'Patient Management',
        Queue::class => 'Queue Management',
        VitalSign::class => 'Medical',
        MedicalRecord::class => 'Medical',
        FollowUp::class => 'Follow-ups',
        Diagnosis::class => 'Diagnosis Management',
        SyndromeCategory::class => 'Syndromic Surveillance',
        SyndromeRuleVersion::class => 'Syndromic Surveillance',
        SurveillanceSetting::class => 'Syndromic Surveillance',
        SurveillanceAlert::class => 'Syndromic Surveillance',
        SurveillanceAlertReview::class => 'Syndromic Surveillance',
        User::class => 'User & Roles',
    ];

    /** @param array<string, mixed> $original @param array<string, mixed> $changes */
    public function recordUpdate(Model $model, array $original, array $changes): void
    {
        $changes = $this->filterChanges($changes);
        if ($changes === []) {
            return;
        }

        $this->write($model, 'UPDATE', $this->filterChanges(array_intersect_key($original, $changes)), $changes, array_keys($changes));
    }

    public function recordDelete(Model $model): void
    {
        $values = $this->filterChanges($model->getAttributes());
        $this->write($model, 'DELETE', $values, [], array_keys($values));
    }

    /** @param array<string, mixed> $previous @param array<string, mixed> $new @param array<int, string> $fields */
    private function write(Model $model, string $action, array $previous, array $new, array $fields): void
    {
        $actor = auth()->user();
        if ($actor === null || ! isset(self::MODULES[$model::class])) {
            return;
        }

        AuditLog::forceCreate([
            'user_id' => $actor->getKey(),
            'user_name' => $actor->name,
            'action' => $action,
            'module' => self::MODULES[$model::class],
            'record_type' => class_basename($model),
            'record_id' => $model->getKey() === null ? null : (string) $model->getKey(),
            'record_description' => $this->description($model),
            'changed_fields' => array_values($fields),
            'previous_values' => $previous,
            'new_values' => $new,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function filterChanges(array $values): array
    {
        return collect($values)
            ->reject(fn (mixed $value, string $field): bool => in_array($field, self::IGNORED_FIELDS, true))
            ->map(fn (mixed $value): mixed => $this->serializable($value))
            ->all();
    }

    private function serializable(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->serializable($item), $value);
        }
        if (is_object($value)) {
            return method_exists($value, 'toArray') ? $value->toArray() : (string) $value;
        }

        return $value;
    }

    private function description(Model $model): string
    {
        $id = (string) ($model->getKey() ?? 'new');
        $name = $this->displayName($model);

        return match ($model::class) {
            User::class => 'User #'.$id.($name !== '' ? ': '.$name : ''),
            PatientInformation::class => 'Patient #'.$id.($name !== '' ? ': '.$name : ''),
            Household::class => 'Family '.($model->family_number ?: '#'.$id),
            Queue::class => 'Queue #'.$id.' for Patient #'.$model->patient_id,
            VitalSign::class => 'Vital signs #'.$id.' for Patient #'.$model->patient_id,
            MedicalRecord::class => 'Medical record #'.$id.' for Patient #'.$model->patient_id,
            FollowUp::class => 'Follow-up #'.$id.' for Patient #'.$model->patient_id,
            PatientProfile::class => 'Registration profile for Patient #'.$model->patient_id,
            Diagnosis::class => 'Diagnosis #'.$id.($name !== '' ? ': '.$name : ''),
            SyndromeCategory::class => 'Syndrome category #'.$id.($name !== '' ? ': '.$name : ''),
            SurveillanceSetting::class => 'Surveillance settings',
            default => class_basename($model).' #'.$id,
        };
    }

    private function displayName(Model $model): string
    {
        if (isset($model->name)) {
            return trim((string) $model->name);
        }

        if ($model instanceof PatientInformation) {
            return trim(implode(' ', array_filter([$model->first_name, $model->middle_name, $model->last_name])));
        }

        return '';
    }
}
