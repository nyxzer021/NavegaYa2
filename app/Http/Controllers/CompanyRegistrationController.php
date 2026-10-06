<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRegistrationRequest;
use App\Models\Organization;
use App\Notifications\VerifyOrganizationContactEmail;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CompanyRegistrationController extends Controller
{
    public function create(): View
    {
        return view('company-registration');
    }

    public function store(StoreCompanyRegistrationRequest $request, CompanyProvisioningService $provisioning): View
    {
        [$organization] = $provisioning->provision($request->validated(), false);
        $url = URL::temporarySignedRoute('company.registration.verify', now()->addHours(48), ['organization' => $organization->id]);
        Notification::route('mail', $organization->email)->notify(new VerifyOrganizationContactEmail($organization, $url));

        return view('company-registration-confirmation', compact('organization'));
    }

    public function verify(Organization $organization): RedirectResponse
    {
        if (! $organization->contact_verified_at) {
            $organization->update(['contact_verified_at' => now()]);
            $organization->users()->where('email', $organization->email)->get()->each(function ($user): void {
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
            });
        }

        return redirect()->route('company.registration')->with('status', 'Correo confirmado. Tu solicitud será revisada por NavegaYA.');
    }
}
