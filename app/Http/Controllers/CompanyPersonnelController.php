<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Support\AdminScope;
use App\Support\CompanyPermissions;
use App\Support\CompanyTeam;
use Illuminate\View\View;

class CompanyPersonnelController extends Controller
{
    public function index(): View
    {
        $organization = Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->where('status', 'active')->firstOrFail();
        $members = User::query()
            ->whereNull('agency_id')
            ->whereHas('roles', fn ($query) => $query->whereIn('code', CompanyTeam::ROLES)->where('role_user.organization_id', $organization->id))
            ->with([
                'organizations' => fn ($query) => $query->whereKey($organization->id),
                'roles' => fn ($query) => $query->where('role_user.organization_id', $organization->id),
            ])
            ->orderBy('name')
            ->get();
        $invitations = StaffInvitation::query()
            ->where('organization_id', $organization->id)
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->with('site')
            ->orderByDesc('id')
            ->get();
        $openCash = CashSession::query()
            ->where('organization_id', $organization->id)
            ->whereNull('closed_at')
            ->with('site')
            ->get()
            ->keyBy('user_id');
        $suspended = $members->filter(fn (User $member): bool => $member->organizations->first()?->pivot->status !== 'active')->count();

        return view('company.personnel.index', [
            'organization' => $organization,
            'members' => $members,
            'invitations' => $invitations,
            'openCash' => $openCash,
            'sites' => OrganizationSite::query()->where('organization_id', $organization->id)->where('is_active', true)->orderBy('name')->get(),
            'profiles' => CompanyPermissions::assignableProfiles(),
            'counts' => [
                'active' => $members->count() - $suspended,
                'invited' => $invitations->count(),
                'suspended' => $suspended,
            ],
        ]);
    }
}
