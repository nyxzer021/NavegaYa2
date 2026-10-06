<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\CompanyFinanceController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanySettlementController extends CompanyFinanceController
{
    public function index(): View
    {
        $this->authorizeManager();

        return parent::index();
    }

    public function updateBank(Request $request): RedirectResponse
    {
        $this->authorizeManager();

        return parent::updateBank($request);
    }

    private function authorizeManager(): void
    {
        abort_unless(auth()->user()->roles()->where('code', 'company_admin')->exists(), 403);
    }
}
