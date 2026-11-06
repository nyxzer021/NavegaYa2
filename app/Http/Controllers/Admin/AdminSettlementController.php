<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\MarketplaceSales;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettlementController extends Controller
{
    public function __construct(private readonly MarketplaceSales $sales) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $payments = $this->sales->ticketPayments()
            ->with([
                'reservation.departure.transportRoute.organization',
                'reservation.airDeparture.airRoute.organization',
            ])
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('paid_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('paid_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('paid_at')
            ->get();
        $current = $payments->filter(fn (Payment $payment): bool => $this->sales->usesPassengerFee($payment));
        $legacy = $payments->reject(fn (Payment $payment): bool => $this->sales->usesPassengerFee($payment))
            ->filter(fn (Payment $payment): bool => (float) $payment->commission_amount > 0);

        $organizations = Organization::query()
            ->where('type', 'transport_company')
            ->whereIn('status', ['active', 'pending'])
            ->orderBy('commercial_name')
            ->get();

        $summaries = $organizations->map(function (Organization $organization) use ($current): object {
            $organizationPayments = $current->filter(
                fn (Payment $payment): bool => $this->organizationId($payment) === $organization->id
            );

            return (object) [
                'organization' => $organization,
                'sales' => (float) $organizationPayments->sum('amount'),
                'fees' => (float) $organizationPayments->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            ];
        });
        $legacySummaries = $organizations->map(function (Organization $organization) use ($legacy): object {
            $organizationPayments = $legacy->filter(
                fn (Payment $payment): bool => $this->organizationId($payment) === $organization->id
            );

            return (object) [
                'organization' => $organization,
                'amount' => (float) $organizationPayments->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            ];
        })->filter(fn (object $summary): bool => $summary->amount > 0)->values();

        return view('admin.settlements.index', [
            'summaries' => $summaries,
            'legacySummaries' => $legacySummaries,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'webSales' => (float) $current->sum('amount'),
            'serviceFees' => (float) $current->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            'legacyTotal' => (float) $legacy->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
        ]);
    }

    public function recordCollection(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'reference' => ['required', 'string', 'max:120'],
        ]);

        $pending = $this->sales->ticketPayments()
            ->whereBetween('paid_at', [Carbon::parse($data['period_start'])->startOfDay(), Carbon::parse($data['period_end'])->endOfDay()])
            ->whereIn('commission_status', ['pending', 'invoiced'])
            ->where(function (Builder $query) use ($organization): void {
                $query->whereHas('reservation.departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organization->id))
                    ->orWhereHas('reservation.airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organization->id));
            })
            ->get();

        $pending = $pending->reject(fn (Payment $payment): bool => $this->sales->usesPassengerFee($payment))
            ->filter(fn (Payment $payment): bool => (float) $payment->commission_amount > 0);
        abort_if($pending->isEmpty(), 422, 'No hay comisiones anteriores pendientes en el período seleccionado.');

        foreach ($pending as $payment) {
            $payment->update([
                'commission_status' => 'paid',
                'commission_paid_at' => now(),
                'commission_reference' => $data['reference'],
            ]);
        }

        return back()->with('success', 'Se marcó la comisión anterior como cobrada. Los importes guardados no cambiaron.');
    }

    private function organizationId(Payment $payment): ?int
    {
        return $payment->reservation?->departure?->transportRoute?->organization_id
            ?? $payment->reservation?->airDeparture?->airRoute?->organization_id;
    }
}
