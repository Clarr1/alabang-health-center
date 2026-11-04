<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Staff sign in &middot; Alabang Health Center</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-blush font-sans text-ink antialiased">
    <a href="#sign-in" class="sr-only focus:not-sr-only focus:absolute focus:z-20 focus:rounded focus:bg-white focus:p-3">Skip to sign in</a>
    <header class="relative z-10 grid items-center gap-3 border-b border-line bg-white px-5 py-4 sm:grid-cols-3 sm:px-10 lg:px-12">
        <a href="{{ url('/') }}" class="flex w-fit items-center gap-2 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-blue" aria-label="Alabang Health Center main entry">
            <img src="{{ asset('images/barangay-alabang.png') }}" alt="" class="h-8 w-8 rounded-full object-contain">
            <span class="text-xs font-semibold">Alabang<span class="mt-0.5 block text-[10px] font-normal">Health Center</span></span>
        </a>
        <div class="flex items-center gap-4 sm:justify-center" aria-label="Official partner logos">
            <img src="{{ asset('images/barangay-alabang.png') }}" alt="Barangay Alabang" class="h-7 w-7 rounded-full object-contain">
            <img src="{{ asset('images/department-of-health.png') }}" alt="Department of Health" class="h-7 w-7 rounded-full object-contain">
            <img src="{{ asset('images/city-of-muntinlupa.png') }}" alt="City of Muntinlupa" class="h-7 w-7 rounded-full object-contain">
        </div>
        <p class="text-[11px] text-muted sm:text-right">City of Muntinlupa &middot; Barangay Alabang</p>
    </header>
    <main class="relative flex flex-1 items-center overflow-hidden px-5 py-10 sm:px-10 lg:py-10">
        <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-3 top-5 w-[390px] max-w-[80%] rounded-full opacity-[0.035]">
        <div class="relative mx-auto grid w-full max-w-[800px] items-center gap-10 lg:grid-cols-[1fr_345px] lg:gap-[76px]">
            <section aria-labelledby="workspace-heading">
                <div class="mb-6 flex h-11 w-11 items-center justify-center rounded-xl bg-brand-blue"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><path d="m12 3 7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg></div>
                <h1 id="workspace-heading" class="text-[27px] font-semibold leading-snug tracking-tight">A secure workspace for patient care.</h1>
                <p class="mt-5 text-sm leading-relaxed text-muted">Sign in with your health center staff account. Select your role, then enter your credentials. Your assigned role determines which records and tools you can access.</p>
                <aside class="mt-5 flex gap-3 rounded-lg border border-line p-3 text-xs leading-relaxed" aria-label="Authorized personnel notice">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg>
                    <div><p class="font-semibold">Authorized personnel only</p><p class="mt-1 text-[11px] text-muted">Patient information is confidential. Never share your sign-in credentials. Role selection at sign-in must match your assigned account role &mdash; selection does not grant permissions.</p></div>
                </aside>
                <p id="administrator-assistance" tabindex="-1" class="mt-5 scroll-mt-5 rounded text-[11px] leading-relaxed text-muted focus:outline focus:outline-2 focus:outline-offset-4 focus:outline-brand-blue">Need access or help with your account? Contact your system administrator through the health center information desk.</p>
            </section>
            <section id="sign-in" class="w-full rounded-xl border border-line bg-white p-5" aria-labelledby="sign-in-heading">
                <h2 id="sign-in-heading" class="text-base font-semibold">Staff sign in</h2>
                <p class="mt-1 text-[11px] text-muted">Select your role and enter your assigned credentials.</p>
                <x-auth-session-status class="mt-4" :status="session('status')" />
                @if ($errors->any())
                    <div id="login-errors" role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800"><ul class="space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <form method="POST" action="{{ route('login') }}" class="mt-4 space-y-3" x-data="{ submitting: false, showPassword: false }" x-on:submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }" x-on:pageshow.window="submitting = false" x-bind:aria-busy="submitting">
                    @csrf
                    <div>
                        <label for="account_type" class="patient-label mb-1.5">Account type <span aria-hidden="true">*</span></label>
                        <select id="account_type" name="account_type" required class="patient-input w-full py-2 text-xs" aria-describedby="account-type-help{{ $errors->any() ? ' login-errors' : '' }}" aria-invalid="{{ $errors->has('account_type') ? 'true' : 'false' }}">
                            <option value="">Select account type</option>
                            @foreach (array_keys(\App\Models\User::ACCOUNT_TYPES) as $accountType)<option value="{{ $accountType }}" @selected(old('account_type') === $accountType)>{{ $accountType }}</option>@endforeach
                        </select>
                        <p id="account-type-help" class="mt-1.5 text-[10px] text-muted">Staff &middot; Healthcare Personnel &middot; Admin</p>
                    </div>
                    <div>
                        <label for="email" class="patient-label mb-1.5">Email address <span aria-hidden="true">*</span></label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" @if ($errors->any()) aria-describedby="login-errors" @endif class="patient-input w-full py-2 text-xs" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    </div>
                    <div>
                        <label for="password" class="patient-label mb-1.5">Password <span aria-hidden="true">*</span></label>
                        <div class="relative">
                            <input id="password" name="password" type="password" x-bind:type="showPassword ? 'text' : 'password'" required autocomplete="current-password" @if ($errors->any()) aria-describedby="login-errors" @endif class="patient-input w-full py-2 pr-12 text-xs" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                            <button type="button" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'" x-bind:aria-pressed="showPassword" aria-label="Show password" aria-controls="password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg hover:bg-blush focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-blue"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M2 12s3-6 10-6 10 6 10 6-3 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
                        </div>
                    </div>
                    <label for="remember" class="flex w-fit items-center gap-2 text-[11px] text-muted"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember')) class="rounded border-line text-ink focus:ring-brand-blue">Remember me</label>
                    <button type="submit" x-bind:disabled="submitting" class="patient-button w-full justify-start border-transparent bg-brand-pink py-2.5 text-xs hover:bg-[#f598ba] disabled:cursor-wait"><span x-show="!submitting">Login</span><span x-show="submitting" x-cloak role="status">Signing in&hellip;</span></button>
                </form>
                <p class="mt-4 text-xs">Forgot password? <a href="#administrator-assistance" class="rounded font-semibold underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-blue">Contact administrator</a></p>
                @if (Route::has('password.request'))<a href="{{ route('password.request') }}" class="mt-2 inline-block rounded text-[11px] text-muted underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-blue">Reset password by email</a>@endif
                <p class="mt-4 rounded-lg bg-blush p-3 text-[10px] leading-relaxed text-muted">Your selected role must match your assigned account role. A mismatch will prevent sign-in. Role selection never grants additional permissions.</p>
                <a href="{{ url('/') }}" class="mt-4 inline-flex items-center gap-2 rounded text-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-blue"><span aria-hidden="true">&larr;</span>Back to main entry</a>
            </section>
        </div>
    </main>
    <footer class="flex flex-wrap justify-between gap-3 px-5 py-7 text-[10px] text-muted sm:px-10 lg:px-12"><p>Alabang Health Center &middot; Patient information is confidential.</p><p>For assistance, visit the information desk.</p></footer>
    @livewireScriptConfig
</body>
</html>
