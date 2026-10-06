<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\CompanyPersonnelController;
use Illuminate\View\View;

class CompanyStaffController extends CompanyPersonnelController
{
    public function index(): View
    {
        abort_unless(auth()->user()->roles()->where('code', 'company_admin')->exists(), 403);

        return parent::index();
    }
}
