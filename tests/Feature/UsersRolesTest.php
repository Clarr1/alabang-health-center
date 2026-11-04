<?php

use App\Livewire\UsersRoles;
use App\Models\PatientInformation;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function newAccountForm(?User $admin = null): Testable
{
    return Livewire::actingAs($admin ?? User::factory()->create(['role' => 'Admin']))->test(UsersRoles::class)
        ->set('firstName', 'New')->set('lastName', 'Staff')->set('username', 'new.staff')->set('email', 'new.staff@example.test')
        ->set('accountType', 'Staff')->set('accountStatus', 'Active')
        ->set('password', 'secure-password')->set('password_confirmation', 'secure-password');
}

it('restricts the page and navigation to active administrators', function (string $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user);
    $response = $this->get(route('users-roles.index'));
    if ($allowed) {
        $response->assertOk()->assertSee('Users &amp; Roles', false)->assertSee('Role Permission Summary');
        expect(preg_match('/<a[^>]*aria-label="Users &amp; Roles"[^>]*aria-current="page"/', $response->getContent()))->toBe(1);
    } else {
        $response->assertForbidden();
        $this->get('/dashboard')->assertDontSee('aria-label="Users &amp; Roles"', false);
        Livewire::test(UsersRoles::class)->assertForbidden();
    }
})->with([['Admin', true], ['Local Health Unit Staff', false], ['Doctor', false]]);

it('requires authentication for the account directory', function () {
    $this->get(route('users-roles.index'))->assertRedirect('/login');
});

it('creates accounts with exactly one existing role and a hashed password', function (string $type, string $role) {
    $form = newAccountForm()->set('accountType', $type)->call('saveAccount')->assertHasNoErrors()
        ->assertSee('Account created successfully.')->assertSet('password', '')->assertSet('editingUserId', null);
    $user = User::where('username', 'new.staff')->sole();
    expect($user->role)->toBe($role)->and($user->is_active)->toBeTrue()
        ->and(Hash::check('secure-password', $user->password))->toBeTrue();
    expect($form->get('accountCounts'))->toBe(['total' => 2, 'active' => 2, 'inactive' => 0]);
})->with([['Staff', 'Local Health Unit Staff'], ['Healthcare Personnel', 'Doctor'], ['Admin', 'Admin']]);

it('validates required fields unique identities roles status and password confirmation', function (string $field, mixed $value, string $error) {
    User::factory()->create(['email' => 'existing@example.test', 'username' => 'existing.user']);
    newAccountForm()->set($field, $value)->call('saveAccount')->assertHasErrors($error);
    expect(User::count())->toBe(2);
})->with([
    ['firstName', '', 'firstName'], ['lastName', '', 'lastName'], ['username', '', 'username'], ['username', 'existing.user', 'username'],
    ['username', 'invalid username', 'username'], ['email', 'existing@example.test', 'email'],
    ['email', 'invalid', 'email'], ['accountType', '', 'accountType'], ['accountType', 'Superadmin', 'accountType'],
    ['accountStatus', 'Unknown', 'accountStatus'], ['password_confirmation', 'different', 'password'], ['password', '', 'password'],
]);

it('edits existing details without exposing or changing the password and preserves authorship', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $user = User::factory()->create();
    $password = $user->password;
    $patient = PatientInformation::factory()->create();
    $vital = VitalSign::create(['patient_id' => $patient->patient_id, 'recorded_by' => $user->getKey(), 'blood_pressure' => '120/80']);
    Livewire::actingAs($admin)->test(UsersRoles::class)->call('editAccount', $user->getKey())
        ->assertSet('firstName', $user->first_name)->assertSet('lastName', $user->last_name)->assertSet('password', '')->assertDontSee($password)
        ->assertDontSee('id="account-password"', false)->set('firstName', 'Updated')->set('lastName', 'name')->set('accountType', 'Healthcare Personnel')
        ->set('accountStatus', 'Inactive')->set('password', 'ignored-password')->call('saveAccount')->assertHasNoErrors();
    expect($user->fresh()->name)->toBe('Updated name')->and($user->fresh()->role)->toBe('Doctor')
        ->and($user->fresh()->is_active)->toBeFalse()->and($user->fresh()->password)->toBe($password)
        ->and($vital->fresh()->recordedBy->getKey())->toBe($user->getKey());
});

it('allows unchanged unique identities and clears verification when the email changes', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $user = User::factory()->create();
    $form = Livewire::actingAs($admin)->test(UsersRoles::class)->call('editAccount', $user->getKey())
        ->call('saveAccount')->assertHasNoErrors();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    $form->call('editAccount', $user->getKey())->set('email', 'changed@example.test')->call('saveAccount')->assertHasNoErrors();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('resets a form on create and cancel without saving unsaved changes', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $user = User::factory()->create();
    $form = Livewire::actingAs($admin)->test(UsersRoles::class)->call('editAccount', $user->getKey())
        ->set('firstName', 'Unsaved')->call('createAccount')->assertSet('editingUserId', null)->assertSet('firstName', '')
        ->set('firstName', 'Another unsaved value')->call('cancel')->assertSet('name', '')->assertSet('password', '');
    expect($user->fresh()->name)->not->toBe('Unsaved')->and(User::count())->toBe(2);
});

it('searches names usernames and emails and resets pagination', function (string $search) {
    $admin = User::factory()->create(['role' => 'Admin', 'name' => 'A Administrator']);
    User::factory()->count(12)->create();
    $target = User::factory()->create(['name' => 'Unique Search Person', 'username' => 'search.target', 'email' => 'target-search@example.test']);
    $form = Livewire::actingAs($admin)->test(UsersRoles::class);
    expect($form->get('accounts')->total())->toBe(14)->and($form->get('accounts')->count())->toBe(10);
    $form->call('nextPage');
    expect($form->get('accounts')->currentPage())->toBe(2)->and($form->get('accounts')->count())->toBe(4);
    $form->set('search', $search)->assertSee($target->name);
    expect($form->get('accounts')->currentPage())->toBe(1)->and($form->get('accounts')->total())->toBe(1);
    $form->set('search', 'no-matching-account-987')->assertSee('No accounts match your search.');
})->with(['Unique Search Person', 'search.target', 'target-search@example.test']);

it('prevents forged account selection IDs', function () {
    $form = newAccountForm();
    expect(fn () => $form->set('editingUserId', 999))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('reauthorizes every account management action after the acting role changes', function (string $action, array $arguments) {
    Notification::fake();
    $form = newAccountForm();
    Livewire::actingAs(User::factory()->create());
    $form->call($action, ...$arguments)->assertForbidden();
    expect(User::count())->toBe(2);
    Notification::assertNothingSent();
})->with([
    ['createAccount', []], ['editAccount', [1]], ['saveAccount', []], ['cancel', []], ['sendPasswordReset', []],
]);

it('checks the current administrator status again inside the save transaction', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $form = newAccountForm($admin);
    User::whereKey($admin->getKey())->update(['is_active' => false]);
    $form->call('saveAccount')->assertForbidden();
    expect(User::count())->toBe(1);
});

it('prevents self deactivation and removing the last active administrator role', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    User::factory()->create(['role' => 'Admin', 'is_active' => false]);
    $form = Livewire::actingAs($admin)->test(UsersRoles::class)->call('editAccount', $admin->getKey())
        ->set('accountStatus', 'Inactive')->call('saveAccount')->assertHasErrors('accountStatus');
    $form->set('accountStatus', 'Active')->set('accountType', 'Staff')->call('saveAccount')->assertHasErrors('accountType');
    expect($admin->fresh()->role)->toBe('Admin')->and($admin->fresh()->is_active)->toBeTrue();
    $this->actingAs($admin)->delete('/profile', ['password' => 'password'])->assertForbidden();
    expect($admin->fresh())->not->toBeNull();
});

it('allows deactivating another administrator when an active administrator remains', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $other = User::factory()->create(['role' => 'Admin']);
    $form = Livewire::actingAs($admin)->test(UsersRoles::class)->call('editAccount', $other->getKey())
        ->set('accountStatus', 'Inactive')->call('saveAccount')->assertHasNoErrors();
    expect($form->get('accountCounts'))->toBe(['total' => 2, 'active' => 1, 'inactive' => 1]);
    expect($admin->fresh()->is_active)->toBeTrue()->and($other->fresh()->is_active)->toBeFalse();
});

it('can relinquish its own admin role when another active administrator exists', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    User::factory()->create(['role' => 'Admin']);
    Livewire::actingAs($admin)->test(UsersRoles::class)->call('editAccount', $admin->getKey())
        ->set('accountType', 'Staff')->call('saveAccount')->assertRedirect(route('dashboard'));
    expect($admin->fresh()->role)->toBe('Local Health Unit Staff');
});

it('also protects the last active administrator in the existing role assignment command', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $this->artisan('users:set-role', ['email' => $admin->email, 'role' => 'Doctor'])->assertFailed();
    expect($admin->fresh()->role)->toBe('Admin');
    User::factory()->create(['role' => 'Admin']);
    $this->artisan('users:set-role', ['email' => $admin->email, 'role' => 'Doctor'])->assertSuccessful();
    expect($admin->fresh()->role)->toBe('Doctor');
});

it('blocks inactive login and protected requests for every supported role', function (string $type, string $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => false]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'account_type' => $type])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    $this->assertGuest();
    $this->actingAs($user)->get('/dashboard')->assertForbidden();
    $this->post('/livewire/update', [])->assertForbidden();
    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
})->with([['Staff', 'Local Health Unit Staff'], ['Healthcare Personnel', 'Doctor'], ['Admin', 'Admin']]);

it('reactivates a user without changing credentials', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $user = User::factory()->create(['is_active' => false]);
    Livewire::actingAs($admin)->test(UsersRoles::class)->call('editAccount', $user->getKey())
        ->set('accountStatus', 'Active')->call('saveAccount')->assertHasNoErrors();
    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => 'password', 'account_type' => 'Staff'])
        ->assertSessionHasNoErrors()->assertRedirect(route('patients.index'));
    $this->assertAuthenticatedAs($user);
});

it('uses the saved email and Laravel broker to send reset notifications with accurate feedback', function () {
    Notification::fake();
    config(['mail.default' => 'smtp']);
    $user = User::factory()->create();
    $form = newAccountForm()->call('editAccount', $user->getKey())->set('email', 'unsaved@example.test')
        ->call('sendPasswordReset')->assertHasNoErrors()->assertSee('accepted by the configured mail transport');
    Notification::assertSentTo($user, ResetPassword::class);
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', 'unsaved@example.test')->exists())->toBeFalse();
    $form->call('sendPasswordReset')->assertHasErrors('reset');
});

it('does not generate or log reset tokens with a non delivering mailer', function () {
    Notification::fake();
    config(['mail.default' => 'log']);
    $user = User::factory()->create();
    newAccountForm()->call('editAccount', $user->getKey())->call('sendPasswordReset')->assertHasErrors('reset')
        ->assertSee('No reset email was sent.');
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
    Notification::assertNothingSent();
});

it('reports mail transport failure without claiming delivery', function () {
    config(['mail.default' => 'smtp']);
    Password::shouldReceive('sendResetLink')->once()->andThrow(new RuntimeException('Test transport failure'));
    $user = User::factory()->create();
    newAccountForm()->call('editAccount', $user->getKey())->call('sendPasswordReset')->assertHasErrors('reset')
        ->assertSee('could not send the reset message')->assertSet('resetFeedback', '');
});

it('backfills existing usernames and status without replacing users or credentials', function () {
    $migration = require database_path('migrations/2026_10_31_161539_add_username_and_is_active_to_users_table.php');
    $migration->down();
    $hash = Hash::make('original-password');
    $id = DB::table('users')->insertGetId(['name' => 'Existing Admin', 'email' => 'legacy@example.test', 'role' => 'Admin', 'password' => $hash], 'user_id');
    $migration->up();
    $user = User::findOrFail($id);
    expect($user->username)->toBe('user-'.$id)->and($user->is_active)->toBeTrue()
        ->and($user->password)->toBe($hash)->and($user->role)->toBe('Admin');
});

it('stores separate multiword names and keeps the existing display name in sync', function () {
    newAccountForm()->set('firstName', ' Maria Elena ')->set('lastName', ' Dela Cruz ')->set('middleName', ' Reyes ')
        ->call('saveAccount')->assertHasNoErrors();
    $user = User::where('username', 'new.staff')->sole();
    expect($user->first_name)->toBe('Maria Elena')->and($user->last_name)->toBe('Dela Cruz')
        ->and($user->middle_name)->toBe('Reyes')->and($user->name)->toBe('Maria Elena Reyes Dela Cruz');
    $admin = User::where('role', 'Admin')->sole();
    Livewire::actingAs($admin)->test(UsersRoles::class)->call('editAccount', $user->getKey())
        ->assertSet('firstName', 'Maria Elena')->assertSet('lastName', 'Dela Cruz')->assertSet('middleName', 'Reyes')
        ->set('middleName', '')->call('saveAccount')->assertHasNoErrors();
    expect($user->fresh()->middle_name)->toBeNull()->and($user->fresh()->name)->toBe('Maria Elena Dela Cruz');
});

it('preserves legacy full names and asks the administrator to confirm separate parts', function () {
    $user = User::factory()->create(['name' => 'Dr. Ana Dela Cruz', 'first_name' => null, 'last_name' => null, 'middle_name' => null]);
    newAccountForm()->call('editAccount', $user->getKey())->assertSet('legacyName', 'Dr. Ana Dela Cruz')
        ->assertSet('firstName', '')->assertSet('lastName', '')->assertSee('Confirm the first, last, and middle names before saving.')
        ->call('saveAccount')->assertHasErrors(['firstName', 'lastName']);
    expect($user->fresh()->name)->toBe('Dr. Ana Dela Cruz');
});

it('clears stale name parts when the existing profile form changes the combined name', function () {
    $user = User::factory()->create(['first_name' => 'Ana', 'last_name' => 'Santos', 'middle_name' => 'Reyes']);
    $this->actingAs($user)->patch('/profile', ['name' => 'Ana Updated Santos', 'email' => $user->email])
        ->assertSessionHasNoErrors();
    expect($user->fresh()->name)->toBe('Ana Updated Santos')->and($user->fresh()->first_name)->toBeNull()
        ->and($user->fresh()->last_name)->toBeNull()->and($user->fresh()->middle_name)->toBeNull();
});
