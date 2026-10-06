<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Notifications\OrganizationActivationInvitation;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminOrganizationReviewController extends Controller
{
    public function index(): View
    {
        return view('admin.organization-reviews', [
            'organizations' => Organization::where('status', 'pending')->latest()->get(),
        ]);
    }

    public function approve(Organization $organization): RedirectResponse
    {
        if (! $organization->contact_verified_at) {
            return back()->with('error', 'No se puede aprobar: el correo de contacto aún no fue confirmado.');
        }
        $organization->update(['status' => 'active', 'verified_at' => now()]);
        $organization->users()->updateExistingPivot($organization->users()->pluck('users.id'), ['status' => 'active']);
        if ($organization->users()->exists()) {
            return back()->with('status', 'Empresa aprobada. La cuenta administradora ya puede ingresar.');
        }
        OrganizationInvitation::where('organization_id', $organization->id)->whereNull('accepted_at')->delete();
        $invitation = OrganizationInvitation::create(['organization_id' => $organization->id, 'email' => $organization->email, 'token' => Str::random(64), 'expires_at' => now()->addDays(3)]);
        Notification::route('mail', $organization->email)->notify(new OrganizationActivationInvitation(route('company.activation', $invitation->token)));

        return back()->with('status', 'Empresa aprobada. Se creó su invitación de activación.');
    }

    public function storeManual(Request $request, CompanyProvisioningService $provisioning): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:180'],
            'commercial_name' => ['nullable', 'string', 'max:180'],
            'ruc' => ['required', 'regex:/^[0-9]{11}$/', 'unique:organizations,ruc'],
            'phone' => ['nullable', 'string', 'max:30'],
            'modality' => ['required', 'in:fluvial,aereo,mixto'],
            'base_city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'admin_name' => ['required', 'string', 'max:180'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        [$organization] = $provisioning->provision($data, true);

        return redirect()->route('admin.organization-directory.show', $organization)
            ->with('success', 'Empresa y administrador raíz creados correctamente.');
    }

    public function reject(Organization $organization): RedirectResponse
    {
        $organization->update(['status' => 'rejected']);

        return back()->with('status', 'Solicitud rechazada.');
    }
}
