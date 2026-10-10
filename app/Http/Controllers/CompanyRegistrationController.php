<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRegistrationRequest;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Notifications\VerifyOrganizationContactEmail;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        $organization = DB::transaction(function () use ($request, $provisioning): Organization {
            $organization = $provisioning->submitApplication($request->validated());
            foreach (['ruc_document' => 'ruc', 'representative_document' => 'representative', 'operating_permit' => 'operating_permit'] as $field => $type) {
                $file = $request->file($field);
                OrganizationDocument::create([
                    'organization_id' => $organization->id,
                    'type' => $type,
                    'path' => $file->store('organization-documents/'.$organization->id),
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }

            return $organization;
        });
        $url = URL::temporarySignedRoute('company.registration.verify', now()->addHours(48), ['organization' => $organization->id]);
        Notification::route('mail', $organization->email)->notify(new VerifyOrganizationContactEmail($organization, $url));

        return view('company-registration-confirmation', compact('organization'));
    }

    public function verify(Organization $organization): RedirectResponse
    {
        if (! $organization->contact_verified_at) {
            $organization->update(['contact_verified_at' => now()]);
        }

        return redirect()->route('company.registration')->with('status', 'Correo confirmado. Tu solicitud será revisada por NavegaYA.');
    }
}
