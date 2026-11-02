<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Payment;
use App\Services\MarketplaceSales;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class CompanyFinanceController extends Controller
{
    public function __construct(private readonly MarketplaceSales $sales) {}

    public function index(): View
    {
        $organization = $this->organization();
        $organization->loadMissing('vessels.basePort');

        $payments = $this->payments($organization)
            ->with(['reservation.departure.transportRoute.originPort', 'reservation.departure.transportRoute.destinationPort', 'reservation.airDeparture.airRoute'])
            ->latest('paid_at')
            ->get();

        $todayPayments = $payments->filter(fn (Payment $payment): bool => $payment->paid_at?->isToday() ?? false);
        $monthPayments = $payments->filter(fn (Payment $payment): bool => $payment->paid_at?->isCurrentMonth() ?? false);

        return view('company.finance.index', [
            'organization' => $organization,
            'grossToday' => (float) $todayPayments->sum('amount'),
            'grossMonth' => (float) $monthPayments->sum('amount'),
            'transactionsMonth' => $monthPayments->count(),
            'averageTicketMonth' => $monthPayments->count() > 0 ? (float) $monthPayments->avg('amount') : 0,
            'confirmedSales' => (float) $payments->sum('amount'),
            'commissionRate' => (float) $organization->commission_rate,
            'commissionGenerated' => (float) $payments->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            'commissionCollected' => (float) $payments->where('commission_status', 'paid')->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            'commissionReceivable' => (float) $payments->whereIn('commission_status', ['pending', 'invoiced'])->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            'recentPayments' => $payments->take(12),
        ]);
    }

    private function organization(): Organization
    {
        return Organization::query()->whereKey(AdminScope::organizationId(auth()->user()))->where('status', 'active')->firstOrFail();
    }

    private function payments(Organization $organization): Builder
    {
        return Payment::query()
            ->whereIn('status', ['confirmed', 'succeeded'])
            ->where(function (Builder $query) use ($organization): void {
                $query->whereHas('reservation.departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organization->id))
                    ->orWhereHas('reservation.airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organization->id));
            });
    }
}
