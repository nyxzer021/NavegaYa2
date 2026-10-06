<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Reservation;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyFinanceController extends Controller
{
    public function index(): View
    {
        $organization = $this->organization();
        $organization->loadMissing('vessels.basePort');
        $confirmed = $this->confirmedReservations($organization);
        $grossToday = (float) (clone $confirmed)->whereDate('updated_at', today())->sum('total_amount');
        $grossMonth = (float) (clone $confirmed)->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_amount');
        $rate = (float) ($organization->commission_rate ?? 8);

        return view('company.finance.index', [
            'organization' => $organization, 'commissionRate' => $rate,
            'grossToday' => $grossToday, 'commissionToday' => round($grossToday * $rate / 100, 2),
            'grossMonth' => $grossMonth, 'commissionMonth' => round($grossMonth * $rate / 100, 2),
            'recentReservations' => (clone $confirmed)->with(['departure.transportRoute.originPort', 'departure.transportRoute.destinationPort', 'airDeparture.airRoute'])->latest('updated_at')->limit(12)->get(),
        ]);
    }

    public function updateBank(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'], 'bank_account' => ['required', 'string', 'max:40'],
            'bank_cci' => ['nullable', 'string', 'max:40'],
        ]);
        $this->organization()->update($data);

        return back()->with('success', 'Cuenta bancaria actualizada.');
    }

    private function organization(): Organization
    {
        return Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->where('status', 'active')->firstOrFail();
    }

    private function confirmedReservations(Organization $organization): Builder
    {
        return Reservation::query()->where('status', 'confirmed')->where(function ($query) use ($organization) {
            $query->whereHas('departure.transportRoute', fn ($route) => $route->where('organization_id', $organization->id))
                ->orWhereHas('airDeparture.airRoute', fn ($route) => $route->where('organization_id', $organization->id));
        });
    }
}
