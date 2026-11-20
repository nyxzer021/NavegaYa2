<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\CompanyPermissions;
use App\Support\CompanyReturnUrl;
use App\Support\CompanyTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        CompanyReturnUrl::remember($request);

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
        $hasCompanyRole = $user->roles()->whereIn('code', CompanyTeam::ROLES)->exists();
        $activeCompany = $user->organizations()
            ->where('organizations.type', 'transport_company')
            ->where('organizations.status', 'active')
            ->wherePivot('status', 'active')
            ->exists();
        if ($hasCompanyRole && ! $activeCompany && ! $user->isCustomer()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => __('staff.suspended_login')]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $photoReturn = CompanyReturnUrl::pull($request);
        if ($photoReturn !== null) {
            return redirect($photoReturn);
        }

        if ($user->isCustomer()) {
            $user->claimGuestPurchases();
        }
        $roles = $user->roles()->pluck('code');
        $profile = CompanyPermissions::profile($user);
        $destination = match ($profile) {
            'seller', 'agency' => route('company.pos.index', absolute: false),
            'admin' => route('company.dashboard', absolute: false),
            'operator' => route('company.departures.index', absolute: false),
            'finance' => route('company.sales.index', absolute: false),
            default => null,
        };
        if ($destination === null) {
            $destination = match (true) {
                $roles->contains('marketplace_admin') => route('marketplace.dashboard', absolute: false),
                $roles->intersect(['customer', 'traveler', 'passenger'])->isNotEmpty() => route('travels.index', absolute: false),
                $roles->intersect(['super_admin', 'admin'])->isNotEmpty() => route('admin.dashboard', absolute: false),
                default => null,
            };
        }

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
