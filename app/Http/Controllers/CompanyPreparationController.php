<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyPreparationController extends Controller
{
    public function show(Request $request, string $page): View
    {
        abort_unless(in_array($page, ['collections', 'refunds', 'payments'], true), 404);
        abort_unless($request->user()->roles()->where('code', 'company_admin')->exists(), 403);

        $organization = $request->user()->organizations()
            ->where('organizations.type', 'transport_company')
            ->whereIn('organizations.status', ['active', 'pending'])
            ->firstOrFail();

        return view('company.preparation', [
            'organization' => $organization,
            'pageKey' => $page,
        ]);
    }
}
