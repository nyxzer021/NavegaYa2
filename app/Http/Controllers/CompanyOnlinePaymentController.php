<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\CompanyPermissions;
use App\Support\PaymentConnections;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyOnlinePaymentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(CompanyPermissions::allows($request->user(), 'company.online-payments.index'), 403);
        $organization = $this->organization();

        return view('company.online-payments.index', [
            'organization' => $organization,
            'rate' => number_format((float) $organization->commission_rate, 2, '.', ''),
            'bankName' => (string) $organization->bank_name,
            'accountPresent' => filled($organization->bank_account),
            'accountTail' => PaymentConnections::tail($organization->bank_account),
            'cciPresent' => filled($organization->bank_cci),
            'cciTail' => PaymentConnections::tail($organization->bank_cci),
        ]);
    }

    private function organization(): Organization
    {
        return auth()->user()->organizations()
            ->where('organizations.type', 'transport_company')
            ->whereIn('organizations.status', ['active', 'pending'])
            ->firstOrFail();
    }
}
