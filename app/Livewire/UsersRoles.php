<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\AccountManagement;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
class UsersRoles extends Component
{
    use WithPagination;

    public string $search = '';

    #[Locked]
    public ?int $editingUserId = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $middleName = '';

    #[Locked]
    public string $legacyName = '';

    public string $username = '';

    public string $email = '';

    public string $accountType = '';

    public string $accountStatus = 'Active';

    public string $password = '';

    public string $password_confirmation = '';

    public string $success = '';

    public string $resetFeedback = '';

    public function mount(): void
    {
        Gate::authorize('manage-accounts');
    }

    public function updatedSearch(): void
    {
        Gate::authorize('manage-accounts');
        $this->resetPage();
    }

    public function createAccount(): void
    {
        Gate::authorize('manage-accounts');
        $this->resetValidation();
        $this->reset('editingUserId', 'firstName', 'lastName', 'middleName', 'legacyName', 'username', 'email', 'accountType', 'accountStatus', 'password', 'password_confirmation', 'resetFeedback', 'success');
        $this->dispatch('account-form-opened');
    }

    public function cancel(): void
    {
        Gate::authorize('manage-accounts');
        $this->createAccount();
    }

    public function editAccount(int $userId): void
    {
        Gate::authorize('manage-accounts');
        $this->createAccount();
        $user = User::findOrFail($userId);
        $this->editingUserId = (int) $user->getKey();
        if (filled($user->first_name) && filled($user->last_name)) {
            $this->firstName = $user->first_name;
            $this->lastName = $user->last_name;
            $this->middleName = $user->middle_name ?? '';
        } else {
            $this->legacyName = $user->name;
        }
        $this->username = $user->username ?? '';
        $this->email = $user->email;
        $this->accountType = array_search($user->role, User::ACCOUNT_TYPES, true) ?: '';
        $this->accountStatus = $user->is_active ? 'Active' : 'Inactive';
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $rules = [
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'middleName' => ['nullable', 'string', 'max:80'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/', Rule::unique('users', 'username')->ignore($this->editingUserId, 'user_id')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingUserId, 'user_id')],
            'accountType' => ['required', Rule::in(array_keys(User::ACCOUNT_TYPES))],
            'accountStatus' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
        if ($this->editingUserId === null) {
            $rules['password'] = ['required', 'string', 'confirmed', Rules\Password::defaults()];
        }

        return $rules;
    }

    public function saveAccount(): void
    {
        Gate::authorize('manage-accounts');
        $this->success = '';
        $this->resetFeedback = '';
        $this->firstName = trim($this->firstName);
        $this->lastName = trim($this->lastName);
        $this->middleName = trim($this->middleName);
        $this->username = mb_strtolower(trim($this->username));
        $this->email = mb_strtolower(trim($this->email));
        $this->validate();
        $creating = $this->editingUserId === null;
        $attributes = [
            'name' => implode(' ', array_filter([$this->firstName, $this->middleName, $this->lastName], fn (string $part): bool => $part !== '')),
            'first_name' => $this->firstName, 'last_name' => $this->lastName, 'middle_name' => $this->middleName !== '' ? $this->middleName : null,
            'username' => $this->username, 'email' => $this->email,
            'role' => User::ACCOUNT_TYPES[$this->accountType], 'is_active' => $this->accountStatus === 'Active',
        ];
        if ($creating) {
            $attributes['password'] = $this->password;
        }

        try {
            $user = app(AccountManagement::class)->save(auth()->user(), $this->editingUserId, $attributes);
        } catch (QueryException) {
            $this->addError('account', 'Unable to save the account. The username or email may already be in use. Refresh and try again.');

            return;
        }

        if ($user->is(auth()->user())) {
            auth()->setUser($user);
            if ($user->role !== 'Admin') {
                $this->redirect(route('dashboard'), navigate: true);
                $this->skipRender();

                return;
            }
        }
        $this->createAccount();
        $this->resetPage();
        unset($this->accounts, $this->accountCounts);
        $this->success = $creating ? 'Account created successfully.' : 'Account updated successfully.';
    }

    public function sendPasswordReset(): void
    {
        Gate::authorize('manage-accounts');
        $this->resetFeedback = '';
        $this->resetErrorBag('reset');
        if ($this->editingUserId === null) {
            $this->addError('reset', 'Select an existing account first.');

            return;
        }
        if (! $this->mailDeliveryAvailable) {
            $this->addError('reset', 'Email delivery is not configured for a delivery-capable mailer. No reset email was sent. Configure SMTP or another supported mail transport.');

            return;
        }
        $user = User::findOrFail($this->editingUserId);
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('reset', 'The mail transport could not send the reset message. Check the email configuration and try again later.');

            return;
        }
        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('reset', __($status));

            return;
        }
        $this->resetFeedback = 'Password reset message accepted by the configured mail transport for '.$user->email.'. Inbox delivery is not confirmed.';
    }

    #[Computed]
    public function mailDeliveryAvailable(): bool
    {
        Gate::authorize('manage-accounts');
        $mailer = config('mail.default');

        return in_array(config('mail.mailers.'.$mailer.'.transport'), ['smtp', 'sendmail', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend'], true);
    }

    #[Computed]
    public function accounts(): LengthAwarePaginator
    {
        Gate::authorize('manage-accounts');
        $search = trim($this->search);

        return User::query()->when($search !== '', function (Builder $query) use ($search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('username', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
            });
        })->orderBy('name')->orderBy('user_id')->paginate(10);
    }

    /** @return array{total: int, active: int, inactive: int} */
    #[Computed]
    public function accountCounts(): array
    {
        Gate::authorize('manage-accounts');
        $total = User::count();
        $active = User::where('is_active', true)->count();

        return ['total' => $total, 'active' => $active, 'inactive' => $total - $active];
    }

    public function render(): View
    {
        Gate::authorize('manage-accounts');

        return view('livewire.users-roles');
    }
}
