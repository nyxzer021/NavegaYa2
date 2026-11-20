<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\CompanyFinanceController;
use App\Support\CompanyPermissions;
use Illuminate\View\View;

class CompanySettlementController extends CompanyFinanceController
{
    public function index(): View
    {
        abort_unless(CompanyPermissions::allows(auth()->user(), 'company.commissions.index'), 403);

        return parent::index();
    }
}
