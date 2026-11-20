<?php

namespace App\Providers;

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
use App\Observers\AuditLogObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('patient-profile-manage', fn (User $user): bool => $user->is_active && $user->hasAuthorizedRole());
        Gate::define('patient-records-view', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Local Health Unit Staff'], true));
        Gate::define('patient-sensitive', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Doctor'], true));
        Gate::define('patient-category-override', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Local Health Unit Staff'], true));
        Gate::define('surveillance-view', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Doctor'], true));
        Gate::define('surveillance-review', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Doctor'], true));
        Gate::define('surveillance-manage', fn (User $user): bool => $user->is_active && $user->role === 'Admin');
        Gate::define('surveillance-approve', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Doctor'], true));
        Gate::define('diagnoses-manage', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Doctor'], true));
        Gate::define('follow-ups-manage', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Doctor'], true));
        Gate::define('manage-accounts', fn (User $user): bool => $user->is_active && $user->role === 'Admin');
        Gate::define('medical-records-view', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Doctor'], true));
        Gate::define('queue-view', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Local Health Unit Staff'], true));
        Gate::define('queue-register', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Local Health Unit Staff'], true));
        Gate::define('queue-consult', fn (User $user): bool => $user->is_active && in_array($user->role, ['Admin', 'Doctor'], true));
        Gate::define('queue-transition', function (User $user, string $status): bool {
            if (! $user->is_active) {
                return false;
            }

            if ($user->role === 'Admin') {
                return true;
            }

            return match ($status) {
                'Called', 'Vital Signs', 'Ready for Consultation', 'No Show', 'Cancelled' => $user->role === 'Local Health Unit Staff',
                'In Consultation', 'Completed' => $user->role === 'Doctor',
                default => false,
            };
        });

        Gate::define('audit-log-view', fn (User $user): bool => $user->is_active && $user->role === 'Admin');

        $observer = app(AuditLogObserver::class);
        foreach ([
            PatientInformation::class,
            PatientProfile::class,
            Household::class,
            PatientCondition::class,
            ImmunizationDose::class,
            Queue::class,
            VitalSign::class,
            MedicalRecord::class,
            FollowUp::class,
            Diagnosis::class,
            SyndromeCategory::class,
            SyndromeRuleVersion::class,
            SurveillanceSetting::class,
            SurveillanceAlert::class,
            SurveillanceAlertReview::class,
            User::class,
        ] as $model) {
            $model::observe($observer);
        }
    }
}
