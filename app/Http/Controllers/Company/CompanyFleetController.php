<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\CompanyFleetController as BaseCompanyFleetController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyFleetController extends BaseCompanyFleetController
{
    public function index(): View
    {
        $this->authorizeManager();

        return parent::index();
    }

    public function storeVessel(Request $request): RedirectResponse
    {
        $this->authorizeManager();

        return parent::storeVessel($request);
    }

    public function storeAircraft(Request $request): RedirectResponse
    {
        $this->authorizeManager();

        return parent::storeAircraft($request);
    }

    private function authorizeManager(): void
    {
        abort_unless(auth()->user()->roles()->where('code', 'company_admin')->exists(), 403);
    }
}
