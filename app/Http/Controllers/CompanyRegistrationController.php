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
                if (! $file) {
                    continue;
                }
                OrganizationDocument::create([
                    'organization_id' => $organization->id,
                    'type' => $type,
                    'path' => $file->store('organization-documents/'.$organization->id),
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }
            foreach ($request->modalityCodes() as $code) {
                $prefix = $code === 'fluvial' ? 'fluvial' : $code;
                $permit = $code === 'fluvial' ? ($request->file('fluvial_permit') ?: $request->file('operating_permit')) : $request->file($prefix.'_permit');
                $aoc = $code === 'fluvial' ? null : $request->file($prefix.'_aoc');
                $organization->modalities()->create([
                    'code' => $code,
                    'status' => 'requested',
                    'permit_scope' => $code === 'fluvial' ? $request->input('fluvial_permit_scope') : null,
                    'permit_number' => $request->input($prefix.'_permit_number'),
                    'permit_expires_on' => $request->input($prefix.'_permit_expires_on'),
                    'permit_path' => $permit?->store('organization-documents/'.$organization->id),
                    'permit_original_name' => $permit?->getClientOriginalName(),
                    'aoc_number' => $code === 'fluvial' ? null : $request->input($prefix.'_aoc_number'),
                    'aoc_expires_on' => $code === 'fluvial' ? null : $request->input($prefix.'_aoc_expires_on'),
                    'aoc_path' => $aoc?->store('organization-documents/'.$organization->id),
                    'aoc_original_name' => $aoc?->getClientOriginalName(),
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
