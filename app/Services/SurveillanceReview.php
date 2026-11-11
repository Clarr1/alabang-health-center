<?php

namespace App\Services;

use App\Models\SurveillanceAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SurveillanceReview
{
    public function record(int $alertId, int $version, string $status, string $notes, ?string $outcome, ?int $reviewerId): SurveillanceAlert
    {
        Gate::authorize('surveillance-review');
        Validator::make(compact('status', 'notes', 'outcome', 'reviewerId'), [
            'status' => ['required', Rule::in(array_diff(SurveillanceAlert::STATUSES, ['New']))],
            'notes' => ['required', 'string', 'min:5', 'max:10000', 'not_regex:/^\s*$/u'],
            'outcome' => [Rule::requiredIf(in_array($status, ['Reviewed', 'Dismissed', 'Resolved', 'Escalated'], true)), 'nullable', 'string', 'max:5000', 'not_regex:/^\s*$/u'],
            'reviewerId' => ['nullable', 'integer', Rule::exists('users', 'user_id')->where('is_active', true)->whereIn('role', ['Admin', 'Doctor'])],
        ])->validate();

        return DB::transaction(function () use ($alertId, $version, $status, $notes, $outcome, $reviewerId): SurveillanceAlert {
            $alert = SurveillanceAlert::whereNotNull('surveillance_result_id')->lockForUpdate()->findOrFail($alertId);
            if ($alert->version !== $version) {
                throw ValidationException::withMessages(['review' => 'This alert changed. Reopen it before saving your review.']);
            }
            $allowed = match ($alert->status) {
                'New' => ['Under Review', 'Escalated', 'Dismissed'],
                'Under Review', 'Escalated' => ['Under Review', 'Reviewed', 'Escalated', 'Dismissed', 'Resolved'],
                'Reviewed' => ['Reviewed', 'Under Review', 'Escalated', 'Resolved'],
                'Dismissed', 'Resolved' => ['Under Review'],
                default => [],
            };
            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['review' => 'Start a review before recording this outcome. Closed alerts must be reopened first.']);
            }
            if ($reviewerId !== null && ! User::whereKey($reviewerId)->where('is_active', true)->whereIn('role', ['Admin', 'Doctor'])->exists()) {
                throw ValidationException::withMessages(['reviewerId' => 'Choose an active doctor or administrator.']);
            }
            $alert->reviews()->create([
                'user_id' => auth()->id(), 'from_status' => $alert->status, 'to_status' => $status,
                'notes' => $notes, 'outcome' => $outcome, 'assigned_to' => $reviewerId,
            ]);
            $alert->update(['status' => $status, 'assigned_to' => $reviewerId, 'version' => $alert->version + 1]);

            return $alert;
        });
    }
}
