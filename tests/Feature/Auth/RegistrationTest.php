<?php

use App\Models\User;

test('public registration screen redirects to sign in', function () {
    $response = $this->get('/register');

    $response->assertRedirect('/login');
});

test('administrators can register new users without replacing their own session', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $response = $this->actingAs($admin)->post('/register', [
        'account_type' => 'Staff',
        'name' => 'Test User',
        'username' => 'test.user',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect(route('register'));
});
