<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Support\CompanyPermissions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyRolesController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $request->user()->organizations()
            ->where('organizations.type', 'transport_company')
            ->whereIn('organizations.status', ['active', 'pending'])
            ->firstOrFail();

        return view('company.roles', [
            'organization' => $organization,
            'profiles' => ['admin', 'seller', 'operator', 'finance'],
            'modules' => array_values(array_filter(
                CompanyPermissions::modules(),
                fn (array $module): bool => $module['visible'] && $module['key'] !== 'lots',
            )),
            'keys' => CompanyPermissions::menuKeys(),
        ]);
    }
}
