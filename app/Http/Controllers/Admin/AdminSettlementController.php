<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettlementController extends Controller
{
    public function index(): View
    {
        $payments = $this->successfulPayments()
            ->with([
                'reservation.departure.transportRoute.organization',
                'reservation.airDeparture.airRoute.organization',
            ])
            ->latest('paid_at')
            ->get();

        $organizations = Organization::query()
            ->where('type', 'transport_company')
            ->whereIn('status', ['active', 'pending'])
            ->orderBy('commercial_name')
            ->get();

        $summaries = $organizations->map(function (Organization $organization) use ($payments): object {
            $organizationPayments = $payments->filter(
                fn (Payment $payment): bool => $this->organizationId($payment) === $organization->id
            );

            return (object) [
                'organization' => $organization,
                'gmv' => (float) $organizationPayments->sum('amount'),
                'generated' => (float) $organizationPayments->sum('commission_amount'),
                'collected' => (float) $organizationPayments->where('commission_status', 'paid')->sum('commission_amount'),
                'pending' => (float) $organizationPayments->whereIn('commission_status', ['pending', 'invoiced'])->sum('commission_amount'),
            ];
        });

        return view('admin.settlements.index', [
            'summaries' => $summaries,
            'grossVolume' => (float) $payments->sum('amount'),
            'commissionGenerated' => (float) $payments->sum('commission_amount'),
            'commissionCollected' => (float) $payments->where('commission_status', 'paid')->sum('commission_amount'),
            'commissionReceivable' => (float) $payments->whereIn('commission_status', ['pending', 'invoiced'])->sum('commission_amount'),
        ]);
    }

    public function recordCollection(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'reference' => ['required', 'string', 'max:120'],
        ]);

        $paymentIds = $this->successfulPayments()
            ->whereBetween('paid_at', [Carbon::parse($data['period_start'])->startOfDay(), Carbon::parse($data['period_end'])->endOfDay()])
            ->whereIn('commission_status', ['pending', 'invoiced'])
            ->where(function (Builder $query) use ($organization): void {
                $query->whereHas('reservation.departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organization->id))
                    ->orWhereHas('reservation.airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organization->id));
            })
            ->pluck('id');

        abort_if($paymentIds->isEmpty(), 422, 'No existen comisiones pendientes para el período seleccionado.');

        Payment::query()->whereKey($paymentIds)->update([
            'commission_status' => 'paid',
            'commission_paid_at' => now(),
            'commission_reference' => $data['reference'],
        ]);

        return back()->with('success', 'Cobro de comisión registrado. No se realizó ninguna transferencia al operador.');
    }

    private function successfulPayments(): Builder
    {
        return Payment::query()->whereIn('status', ['confirmed', 'succeeded']);
    }

    private function organizationId(Payment $payment): ?int
    {
        return $payment->reservation?->departure?->transportRoute?->organization_id
            ?? $payment->reservation?->airDeparture?->airRoute?->organization_id;
    }
}
