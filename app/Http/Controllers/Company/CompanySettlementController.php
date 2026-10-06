<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\CompanyFinanceController;
use Illuminate\View\View;

class CompanySettlementController extends CompanyFinanceController
{
    public function index(): View
    {
        abort_unless(auth()->user()->roles()->where('code', 'company_admin')->exists(), 403);

        return parent::index();
    }
}
