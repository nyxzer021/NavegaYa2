<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRegistrationRequest;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CompanyRegisterController extends Controller
{
    public function create(): View
    {
        return view('company-registration');
    }

    public function register(StoreCompanyRegistrationRequest $request, CompanyProvisioningService $provisioning): RedirectResponse
    {
        [, $user] = $provisioning->provision($request->validated(), false);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('company.departures.index')
            ->with('success', 'Tu empresa fue registrada. Completa la configuración mientras validamos tus documentos.');
    }
}
