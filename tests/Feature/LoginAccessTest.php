<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

it('labels email authentication accurately without unsupported audit claims', function () {
    $this->get('/login')->assertOk()->assertSee('A secure workspace for patient care.')
        ->assertSee('Staff')->assertSee('Healthcare Personnel')->assertSee('Admin')->assertSee('Email address')
        ->assertDontSee('Username or email')->assertDontSee('Activity is recorded in the audit log');
});

it('authenticates each assigned role without changing permissions and regenerates the session', function (string $type, string $role, string $destination) {
    $user = User::factory()->create(['role' => $role]);
    $sessionId = session()->getId();
    $this->post('/login', ['account_type' => $type, 'email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route($destination));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->role)->toBe($role)->and(session()->getId())->not->toBe($sessionId);
})->with([
    ['Staff', 'Local Health Unit Staff', 'patients.index'],
    ['Healthcare Personnel', 'Doctor', 'doctors-consultation.index'],
    ['Admin', 'Admin', 'dashboard'],
]);

it('returns the same generic error for invalid credentials role mismatch and missing accounts', function (string $failure) {
    $user = User::factory()->create(['role' => 'Local Health Unit Staff']);
    $this->post('/login', [
        'account_type' => $failure === 'mismatch' ? 'Admin' : 'Staff',
        'email' => $failure === 'missing' ? 'missing@example.test' : $user->email,
        'password' => $failure === 'password' ? 'incorrect' : 'password',
        'role' => 'Admin',
    ])->assertSessionHasErrors(['email' => trans('auth.failed')]);
    $this->assertGuest();
    expect($user->fresh()->role)->toBe('Local Health Unit Staff');
})->with(['mismatch', 'missing', 'password']);

it('rejects unsupported stored roles under every offered account type', function (string $type) {
    DB::statement('PRAGMA ignore_check_constraints = ON');
    try {
        $user = User::factory()->create(['role' => 'Unsupported']);
    } finally {
        DB::statement('PRAGMA ignore_check_constraints = OFF');
    }
    $this->post('/login', ['account_type' => $type, 'email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    $this->assertGuest();
})->with(['Staff', 'Healthcare Personnel', 'Admin']);

it('requires a supported account type', function (mixed $type) {
    $user = User::factory()->create();
    $this->post('/login', ['account_type' => $type, 'email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('account_type');
    $this->assertGuest();
})->with([null, 'Doctor', 'Superadmin', [['Admin']]]);

it('throttles mismatched role attempts and accepts valid credentials after the limit clears', function () {
    $user = User::factory()->create();
    $data = ['account_type' => 'Admin', 'email' => $user->email, 'password' => 'password'];
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', $data)->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }
    $this->post('/login', array_merge($data, ['account_type' => 'Staff']))->assertSessionHasErrors('email');
    $this->assertGuest();
    RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
    $this->post('/login', array_merge($data, ['account_type' => 'Staff']))->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
});

it('honors only authorized local intended destinations', function (string $type, string $role, string $intended, string $expected) {
    $user = User::factory()->create(['role' => $role]);
    $this->withSession(['url.intended' => $intended])->post('/login', [
        'account_type' => $type, 'email' => $user->email, 'password' => 'password',
    ])->assertRedirect(url($expected));
})->with([
    ['Staff', 'Local Health Unit Staff', '/queues', '/queues'],
    ['Staff', 'Local Health Unit Staff', '/doctors-consultation', '/patients'],
    ['Staff', 'Local Health Unit Staff', '/register', '/patients'],
    ['Healthcare Personnel', 'Doctor', '/patient-information-form', '/doctors-consultation'],
    ['Admin', 'Admin', '/register', '/register'],
    ['Admin', 'Admin', 'https://untrusted.example/dashboard', '/dashboard'],
    ['Admin', 'Admin', '//untrusted.example/dashboard', '/dashboard'],
    ['Staff', 'Local Health Unit Staff', '/does-not-exist', '/patients'],
]);

it('denies unsupported or missing roles on pages and Livewire updates while allowing logout', function (mixed $role) {
    $user = User::factory()->create();
    $user->role = $role;
    $user->syncOriginalAttribute('role');
    $this->actingAs($user);
    foreach (['/dashboard', '/patients', '/queues', '/patients/1/card', '/profile'] as $path) {
        $this->get($path)->assertForbidden();
    }
    $this->post('/livewire/update', [])->assertForbidden();
    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
})->with(['Unsupported', null]);

it('enforces role permissions on direct workflow routes', function (string $role, string $path) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get($path)->assertForbidden();
})->with([
    ['Local Health Unit Staff', '/doctors-consultation'],
    ['Doctor', '/patient-information-form'],
    ['Doctor', '/patient-vital-signs'],
    ['Doctor', '/register'],
    ['Local Health Unit Staff', '/register'],
]);

it('invalidates the session on logout and requires sign in again', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['private-test' => 'value'])->post('/logout')
        ->assertRedirect('/')->assertSessionMissing('private-test');
    $this->assertGuest();
    $this->get('/patients')->assertRedirect('/login');
});

it('prevents public self registration from creating accounts', function () {
    $this->get('/register')->assertRedirect('/login');
    $this->post('/register', [
        'name' => 'Unauthorized', 'email' => 'unauthorized@example.test', 'account_type' => 'Admin',
        'password' => 'password', 'password_confirmation' => 'password', 'role' => 'Admin',
    ])->assertRedirect('/login');
    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('allows administrators to create explicitly assigned accounts without switching sessions', function (string $type, string $role) {
    $admin = User::factory()->create(['role' => 'Admin']);
    $this->actingAs($admin)->get('/register')->assertOk()->assertSee('Create staff account');
    $this->post('/register', [
        'name' => 'New User', 'username' => 'new.user', 'email' => 'new@example.test', 'account_type' => $type,
        'password' => 'password', 'password_confirmation' => 'password', 'role' => 'Admin',
    ])->assertRedirect(route('register'))->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($admin);
    expect(User::where('email', 'new@example.test')->sole()->role)->toBe($role);
})->with([['Staff', 'Local Health Unit Staff'], ['Healthcare Personnel', 'Doctor'], ['Admin', 'Admin']]);

it('prevents non administrators from posting new accounts directly', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->post('/register', [
        'name' => 'Unauthorized', 'email' => 'unauthorized@example.test', 'account_type' => 'Admin',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertForbidden();
    expect(User::count())->toBe(1);
})->with(['Doctor', 'Local Health Unit Staff']);

it('requires an explicit supported type when an administrator creates an account', function (mixed $type) {
    $this->actingAs(User::factory()->create(['role' => 'Admin']))->post('/register', [
        'name' => 'New User', 'email' => 'new@example.test', 'account_type' => $type,
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasErrors('account_type');
    expect(User::count())->toBe(1);
})->with([null, 'Superadmin']);
