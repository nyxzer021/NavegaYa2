<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Support\AdminScope;
use Illuminate\View\View;

class CompanyPersonnelController extends Controller
{
    public function index(): View
    {
        $organization = Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->where('status', 'active')->firstOrFail();
        $organization->loadMissing('vessels.basePort');

        return view('company.personnel.index', [
            'organization' => $organization,
            'counterStaff' => User::query()->whereHas('organizations', fn ($query) => $query->whereKey($organization->id))
                ->whereHas('roles', fn ($query) => $query->where('code', 'company_counter')->where('role_user.organization_id', $organization->id))
                ->with(['organizations' => fn ($query) => $query->whereKey($organization->id)])->orderBy('name')->get(),
        ]);
    }
}
