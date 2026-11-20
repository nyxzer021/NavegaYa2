<?php

namespace App\Http\Controllers;

use App\Support\CompanyPermissions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyPreparationController extends Controller
{
    /**
     * @var list<string>
     */
    private const PAGES = [
        'collections', 'refunds', 'payments', 'company_details', 'branches', 'agencies',
        'parcels', 'freight', 'cash_close', 'agency_commissions', 'receipts', 'reports',
        'audit', 'page_sections',
        'gallery', 'policies', 'appearance', 'whatsapp',
    ];

    public function show(Request $request, string $page): View
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        abort_unless(CompanyPermissions::allows($request->user(), $request->route()?->getName()), 403);

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
