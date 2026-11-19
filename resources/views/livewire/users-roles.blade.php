<div class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8" x-data x-on:account-form-opened.window="$nextTick(() => { $refs.accountName.focus({ preventScroll: true }); $refs.accountForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); })">
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-4 top-5 w-[440px] max-w-[70%] rounded-full opacity-[0.035]">
    <header class="relative mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-semibold tracking-tight sm:text-[28px]">Users &amp; Roles</h1><p class="mt-2 text-sm text-muted">Maintain staff access. Each account is assigned exactly one role.</p></div>
        <button type="button" wire:click="createAccount" wire:loading.attr="disabled" class="patient-button border-transparent bg-brand-pink text-xs hover:bg-[#f598ba]"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M19 8v6m-3-3h6"/></svg>Create staff account</button>
    </header>
    @if ($success)<div role="status" class="relative mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $success }}</div>@endif
    <div class="relative grid min-w-0 items-start gap-5 lg:grid-cols-[minmax(0,1.65fr)_minmax(280px,1fr)]">
        <div class="min-w-0 space-y-5">
            <section class="patient-card min-w-0" aria-labelledby="account-directory-title">
                @php($counts = $this->accountCounts)
                @php($accounts = $this->accounts)
                <h2 id="account-directory-title" class="text-base font-semibold">Staff accounts</h2>
                <p class="mt-1 text-xs text-muted">{{ number_format($counts['total']) }} accounts &middot; {{ number_format($counts['active']) }} active &middot; {{ number_format($counts['inactive']) }} inactive</p>
                <label for="account-search" class="patient-label mt-5">Search staff name, username, or email</label>
                <input id="account-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search accounts..." class="patient-input w-full text-xs" autocomplete="off">
                <p wire:loading wire:target="search,gotoPage,nextPage,previousPage,saveAccount" role="status" class="mt-3 text-xs text-muted">Loading accounts...</p>
                <div class="mt-4 overflow-x-auto" wire:loading.class="opacity-50" wire:target="search,gotoPage,nextPage,previousPage,saveAccount">
                    <table class="w-full min-w-[520px] text-left text-xs">
                        <caption class="sr-only">Account directory, assigned roles, and status</caption>
                        <thead class="bg-blush text-[11px]"><tr><th scope="col" class="px-3 py-3 font-semibold">Name / Username</th><th scope="col" class="px-3 py-3 font-semibold">Assigned Role</th><th scope="col" class="px-3 py-3 font-semibold">Status</th><th scope="col" class="px-3 py-3 font-semibold">Edit</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($accounts as $account)
                                <tr wire:key="account-{{ $account->getKey() }}" @class(['hover:bg-blush/60', 'bg-blush' => $editingUserId === $account->getKey()])>
                                    <td class="px-3 py-4"><span class="block font-semibold">{{ $account->name }}</span><span class="mt-1 block text-[11px] text-muted">{{ $account->username ?: 'Username not set' }}</span><span class="mt-1 block text-[10px] text-muted">{{ $account->email }}</span></td>
                                    <td class="px-3 py-4">{{ array_search($account->role, \App\Models\User::ACCOUNT_TYPES, true) ?: 'Unassigned / unsupported' }}</td>
                                    <td class="px-3 py-4"><span @class(['rounded px-2 py-1 text-[10px] font-semibold', 'bg-[#e8eaf0]' => $account->is_active, 'bg-blush text-muted' => ! $account->is_active])>{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="px-3 py-4"><button type="button" wire:click="editAccount({{ $account->getKey() }})" wire:loading.attr="disabled" aria-label="Edit {{ $account->name }}" class="rounded px-2 py-1 font-medium hover:bg-brand-pink/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-blue">Edit</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-10 text-center text-muted">{{ $search !== '' ? 'No accounts match your search.' : 'No accounts found.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Account pagination" class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-[11px] text-muted">Showing {{ $accounts->firstItem() ?? 0 }}&ndash;{{ $accounts->lastItem() ?? 0 }} of {{ $accounts->total() }}</p>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" wire:loading.attr="disabled" @disabled($accounts->onFirstPage()) class="patient-button px-3 py-2 text-xs">Previous</button>
                        <span class="rounded-lg bg-brand-blue px-3 py-2 text-xs" aria-current="page">{{ $accounts->currentPage() }}</span>
                        <button type="button" wire:click="nextPage" wire:loading.attr="disabled" @disabled(! $accounts->hasMorePages()) class="patient-button px-3 py-2 text-xs">Next</button>
                    </div>
                </nav>
            </section>
            <aside class="rounded-lg border border-line bg-blush/80 p-4 text-xs leading-relaxed"><p class="font-semibold">Deactivate accounts to preserve history</p><p class="mt-1 text-muted">Clinical authorship, recorded-by relationships, and queue history are retained. Account change auditing is not currently implemented.</p></aside>
        </div>
        <div class="min-w-0 space-y-5">
            <section class="patient-card" x-ref="accountForm" aria-labelledby="account-form-title" wire:key="account-form-{{ $editingUserId ?? 'new' }}">
                <h2 id="account-form-title" class="text-base font-semibold">{{ $editingUserId === null ? 'Create staff account' : 'Edit staff account' }}</h2>
                <p class="mt-1 text-xs text-muted">{{ $editingUserId === null ? 'Enter the assigned credentials for a new account.' : 'Update account details. The existing password will be preserved.' }}</p>
                @if ($errors->any())<div id="account-errors" role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800"><ul class="space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <form wire:submit="saveAccount" class="mt-5 space-y-4" aria-label="Account details">
                    @if ($legacyName)<p class="rounded-lg bg-blush p-3 text-xs leading-relaxed text-muted">Existing full name: <strong>{{ $legacyName }}</strong>. Confirm the first, last, and middle names before saving.</p>@endif
                    @foreach (['firstName' => 'First Name', 'lastName' => 'Last Name', 'middleName' => 'Middle Name', 'username' => 'Username', 'email' => 'Email'] as $field => $label)
                        <div><label for="account-{{ $field }}" class="patient-label">{{ $label }} {{ $field === 'middleName' ? '(optional)' : '*' }}</label><input id="account-{{ $field }}" wire:model="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" @required($field !== 'middleName') maxlength="{{ in_array($field, ['firstName', 'lastName', 'middleName']) ? 80 : ($field === 'username' ? 60 : 255) }}" @if ($field === 'firstName') x-ref="accountName" @endif autocomplete="{{ $field === 'email' ? 'email' : 'off' }}" class="patient-input w-full text-xs" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @if ($errors->any()) aria-describedby="account-errors" @endif>
                        @if ($field === 'username')<p class="mt-1 text-[10px] leading-relaxed text-muted">Letters, numbers, dots, dashes, and underscores. Sign in with email.</p>@endif</div>
                    @endforeach
                    <div><label for="account-type" class="patient-label">Assigned Role *</label><select id="account-type" wire:model="accountType" required class="patient-input w-full text-xs" aria-invalid="{{ $errors->has('accountType') ? 'true' : 'false' }}"><option value="">Select one role</option>@foreach (array_keys(\App\Models\User::ACCOUNT_TYPES) as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
                    <div><label for="account-status" class="patient-label">Account Status *</label><select id="account-status" wire:model="accountStatus" required class="patient-input w-full text-xs" aria-invalid="{{ $errors->has('accountStatus') ? 'true' : 'false' }}"><option value="Active">Active</option><option value="Inactive" @disabled($editingUserId === auth()->id())>Inactive</option></select><p class="mt-1 text-[10px] leading-relaxed text-muted">Inactive accounts cannot sign in. You cannot deactivate yourself, and one active Admin must remain.</p></div>
                    @if ($editingUserId === null)
                        @foreach (['password' => 'Password', 'password_confirmation' => 'Confirm Password'] as $field => $label)
                            <div wire:key="account-password-visibility-{{ $field }}" x-data="{ visible: false }" x-on:account-form-opened.window="visible = false">
                                <label for="account-{{ $field }}" class="patient-label">{{ $label }} *</label>
                                <div class="relative">
                                    <input id="account-{{ $field }}" wire:model="{{ $field }}" type="password" x-bind:type="visible ? 'text' : 'password'" required autocomplete="new-password" class="patient-input w-full pr-12 text-xs" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}">
                                    <button type="button" x-on:click="visible = !visible" x-bind:aria-pressed="visible" aria-controls="account-{{ $field }}" aria-label="Show {{ strtolower($label) }}" x-bind:aria-label="visible ? 'Hide {{ strtolower($label) }}' : 'Show {{ strtolower($label) }}'" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg hover:bg-blush focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-blue"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M2 12s3-6 10-6 10 6 10 6-3 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
                                </div>
                            </div>
                        @endforeach
                    @endif
                    <div class="flex flex-wrap gap-2"><button type="submit" wire:loading.attr="disabled" wire:target="saveAccount,sendPasswordReset" class="patient-button border-transparent bg-brand-pink text-xs hover:bg-[#f598ba]"><span wire:loading.remove wire:target="saveAccount">{{ $editingUserId === null ? 'Create Account' : 'Save Changes' }}</span><span wire:loading wire:target="saveAccount">Saving...</span></button><button type="button" wire:click="cancel" wire:loading.attr="disabled" class="patient-button text-xs">Cancel</button></div>
                </form>
                @if ($editingUserId !== null)
                    <button type="button" wire:click="sendPasswordReset" wire:confirm="Send a password reset message to this account's saved email address?" wire:loading.attr="disabled" wire:target="sendPasswordReset,saveAccount" class="patient-button mt-3 text-xs"><span wire:loading.remove wire:target="sendPasswordReset">Send password reset</span><span wire:loading wire:target="sendPasswordReset">Requesting reset...</span></button>
                    <p class="mt-2 text-[10px] leading-relaxed text-muted">Uses the saved email address. Unsaved changes are not included.</p>
                    @if (! $this->mailDeliveryAvailable)<p class="mt-2 text-[10px] leading-relaxed text-muted">Email delivery needs configuration. The current mailer cannot deliver reset emails; configure SMTP or another delivery-capable mailer.</p>@endif
                @endif
                @if ($resetFeedback)<p role="status" class="mt-3 rounded-lg bg-blush p-3 text-xs leading-relaxed">{{ $resetFeedback }}</p>@endif
            </section>
            <aside class="patient-card" aria-labelledby="permission-summary-title"><h2 id="permission-summary-title" class="text-base font-semibold">Role Permission Summary</h2>
                <dl class="mt-5 space-y-4 text-xs leading-relaxed">
                    <div><dt class="font-semibold">Staff</dt><dd class="mt-2 text-muted">Register and edit patient demographics, check patients in, manage assessment queue stages, record vital signs, and view patient records.</dd></div>
                    <div><dt class="font-semibold">Healthcare Personnel</dt><dd class="mt-2 text-muted">Access medical records, consultations, follow-ups, and syndromic surveillance.</dd></div>
                    <div><dt class="font-semibold">Admin</dt><dd class="mt-2 text-muted">Full access to all modules, administrative functions, and user and role management.</dd></div>
                </dl>
            </aside>
        </div>
    </div>
</div>
