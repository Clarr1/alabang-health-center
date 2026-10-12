<?php

use App\Models\User;

it('assigns supported roles to an existing account', function (string $role) {
    $user = User::factory()->create();
    $this->artisan('users:set-role', ['email' => $user->email, 'role' => $role])->assertSuccessful();
    expect($user->fresh()->role)->toBe($role);
})->with(['Admin', 'Local Health Unit Staff', 'Doctor']);

it('rejects invalid roles and nonexistent accounts', function () {
    $user = User::factory()->create();
    $this->artisan('users:set-role', ['email' => $user->email, 'role' => 'Patient'])->assertFailed();
    expect($user->fresh()->role)->toBe('Local Health Unit Staff');
    $this->artisan('users:set-role', ['email' => 'missing@example.test', 'role' => 'Doctor'])->assertFailed();
});

it('keeps role changes out of user mass assignment', function () {
    $user = User::factory()->create();
    $user->fill(['role' => 'Admin'])->save();
    expect($user->fresh()->role)->toBe('Local Health Unit Staff');
});
