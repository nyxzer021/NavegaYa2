<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            if ($request->user()->isCustomer()) {
                $request->user()->claimGuestPurchases();
            }
            $destination = $request->user()->isCustomer() ? route('travels.index', absolute: false) : route('dashboard', absolute: false);

            return redirect()->intended($destination.'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        $isCustomer = $request->user()->isCustomer();
        if ($isCustomer) {
            $request->user()->claimGuestPurchases();
        }
        $destination = $isCustomer ? route('travels.index', absolute: false) : route('dashboard', absolute: false);

        return redirect()->intended($destination.'?verified=1');
    }
}
