<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $fallback = match ($request->user()->role) {
            'Doctor' => route('doctors-consultation.index'),
            'Local Health Unit Staff' => route('patients.index'),
            default => route('dashboard'),
        };
        $intended = $request->session()->pull('url.intended');

        return redirect()->to($this->authorizedDestination($request, $intended) ? $intended : $fallback);
    }

    private function authorizedDestination(Request $request, mixed $destination): bool
    {
        if (! is_string($destination) || str_contains($destination, '\\')
            || (! str_starts_with($destination, $request->getSchemeAndHttpHost().'/')
                && (! str_starts_with($destination, '/') || str_starts_with($destination, '//')))) {
            return false;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($destination));
        } catch (HttpException) {
            return false;
        }

        $ability = match ($route->getName()) {
            'dashboard', 'profile.edit', 'password.confirm' => null,
            'patients.index', 'patients.card.show', 'patients.card.download', 'queues.index', 'queues.display', 'medical-records.index', 'medical-records.show' => 'queue-view',
            'patient.information-form', 'patients.edit', 'patient.vital-signs-form', 'queues.vital-signs' => 'queue-register',
            'doctors-consultation.index', 'queues.consultation', 'medical-form.index' => 'queue-consult',
            'register', 'users-roles.index' => 'manage-accounts',
            default => false,
        };

        return $ability === null || ($ability !== false && Gate::allows($ability));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
