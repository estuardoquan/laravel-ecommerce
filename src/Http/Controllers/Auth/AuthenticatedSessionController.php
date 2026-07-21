<?php

namespace EQ\LaravelEcommerce\Http\Controllers\Auth;

use Illuminate\Http\Request;
use EQ\LaravelEcommerce\Http\Controllers\Controller;
use EQ\LaravelEcommerce\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Render the login view
     */
    public function render(Request $request): InertiaResponse
    {
        return Inertia::render('auth/LoginView', []);
    }
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): HttpResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $redirect = redirect()->intended();

        if (! $request->inertia()) {
            return $redirect;
        }

        return Inertia::location($redirect);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): HttpResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return Inertia::location('/');
    }
}
