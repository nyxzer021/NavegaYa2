<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\CompanyFleetController as BaseCompanyFleetController;
use App\Support\CompanyPermissions;
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

    public function updateTransport(Request $request): RedirectResponse
    {
        $this->authorizeManager();

        return parent::updateTransport($request);
    }

    private function authorizeManager(): void
    {
        abort_unless(CompanyPermissions::allows(auth()->user(), request()->route()?->getName()), 403);
    }
}
