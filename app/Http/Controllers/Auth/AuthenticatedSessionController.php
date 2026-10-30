<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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

        $user = $request->user();
        if ($user->isCustomer()) {
            $user->claimGuestPurchases();
        }
        $roles = $user->roles()->pluck('code');
        $destination = match (true) {
            $roles->contains('company_counter') => route('company.counter.index', absolute: false),
            $roles->contains('company_admin') => route('company.dashboard', absolute: false),
            $roles->contains('marketplace_admin') => route('marketplace.dashboard', absolute: false),
            $roles->intersect(['customer', 'traveler', 'passenger'])->isNotEmpty() => route('travels.index', absolute: false),
            $roles->intersect(['super_admin', 'admin'])->isNotEmpty() => route('admin.dashboard', absolute: false),
            default => null,
        };

        if ($destination !== null) {
            $request->session()->forget('url.intended');

            return redirect($destination);
        }

        return redirect()->intended(route('dashboard', absolute: false));
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
