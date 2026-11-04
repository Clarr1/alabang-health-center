<?php

use App\Models\User;
use App\Services\AccountManagement;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:set-role {email} {role}', function (): int {
    $role = (string) $this->argument('role');

    if (! in_array($role, ['Admin', 'Local Health Unit Staff', 'Doctor'], true)) {
        $this->error('Role must be Admin, Local Health Unit Staff, or Doctor.');

        return 1;
    }

    $user = User::where('email', $this->argument('email'))->first();

    if ($user === null) {
        $this->error('User not found.');

        return 1;
    }

    try {
        app(AccountManagement::class)->assignRole($user, $role);
    } catch (ValidationException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }
    $this->info("Role updated to {$role}.");

    return 0;
})->purpose('Assign a health center role to an existing account');

Schedule::command('follow-ups:dispatch')->everyMinute()->withoutOverlapping();
