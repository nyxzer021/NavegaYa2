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
        $current = $payments->filter(fn (Payment $payment): bool => $this->sales->usesPassengerFee($payment));
        $legacy = $payments->reject(fn (Payment $payment): bool => $this->sales->usesPassengerFee($payment))
            ->filter(fn (Payment $payment): bool => (float) $payment->commission_amount > 0);

        return view('company.finance.index', [
            'organization' => $organization,
            'companyFare' => (float) $current->sum(fn (Payment $payment): float => $this->sales->fareOf($payment)),
            'commissionRate' => (float) $organization->commission_rate,
            'travelerFees' => (float) $current->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            'salesCount' => $current->count(),
            'recentPayments' => $current->take(12)->map(fn (Payment $payment): array => [
                'code' => $payment->reservation?->code ?? $payment->provider_reference,
                'paid_at' => $payment->paid_at,
                'channel' => $payment->reservation?->sales_channel === 'counter' ? 'Muelle' : 'En línea',
                'fare' => $this->sales->fareOf($payment),
                'fee' => $this->sales->commissionOf($payment),
            ]),
            'legacyPayments' => $legacy->map(fn (Payment $payment): array => [
                'code' => $payment->reservation?->code ?? $payment->provider_reference,
                'paid_at' => $payment->paid_at,
                'fare' => $this->sales->fareOf($payment),
                'commission' => $this->sales->commissionOf($payment),
            ]),
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
