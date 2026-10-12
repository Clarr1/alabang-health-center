<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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

    $user->forceFill(['role' => $role])->save();
    $this->info("Role updated to {$role}.");

    return 0;
})->purpose('Assign a health center role to an existing account');
