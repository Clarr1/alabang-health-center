<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountManagement
{
    /** @param array{name: string, first_name: string, last_name: string, middle_name: ?string, username: string, email: string, role: string, is_active: bool, password?: string} $attributes */
    public function save(User $actor, ?int $userId, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $userId, $attributes): User {
            $administrators = User::where('role', 'Admin')->orderBy('user_id')->lockForUpdate()->get();
            Gate::forUser(User::findOrFail($actor->getKey()))->authorize('manage-accounts');
            $user = $userId === null ? new User : User::whereKey($userId)->lockForUpdate()->firstOrFail();

            if ($userId !== null) {
                if ($user->is($actor) && ! $attributes['is_active']) {
                    throw ValidationException::withMessages(['accountStatus' => 'You cannot deactivate your own account.']);
                }
                $this->protectLastAdministrator($user, $attributes['role'], $attributes['is_active'], $administrators);
            }

            $user->forceFill($attributes);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            if (! $user->is_active || $user->isDirty('role')) {
                $user->remember_token = Str::random(60);
            }
            $user->save();

            return $user;
        }, 3);
    }

    public function assignRole(User $user, string $role): void
    {
        abort_unless(in_array($role, User::ACCOUNT_TYPES, true), 422);
        DB::transaction(function () use ($user, $role): void {
            $administrators = User::where('role', 'Admin')->orderBy('user_id')->lockForUpdate()->get();
            $account = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $this->protectLastAdministrator($account, $role, $account->is_active, $administrators);
            $account->forceFill(['role' => $role, 'remember_token' => Str::random(60)])->save();
        }, 3);
    }

    /** @param Collection<int, User> $administrators */
    private function protectLastAdministrator(User $user, string $role, bool $active, Collection $administrators): void
    {
        if ($user->role === 'Admin' && $user->is_active && ($role !== 'Admin' || ! $active)
            && $administrators->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['accountType' => 'Keep at least one active administrator. Activate another Admin account first.']);
        }
    }
}
