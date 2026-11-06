<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\MarketplaceSales;
use App\Support\AuditRecorder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        $legacyOpen = $legacy->reject(fn (Payment $payment): bool => $payment->commission_status === 'voided');

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

        return view('admin.settlements.index', [
            'summaries' => $summaries,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'webSales' => (float) $current->sum('amount'),
            'serviceFees' => (float) $current->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            'legacyTotal' => (float) $legacyOpen->sum(fn (Payment $payment): float => $this->sales->commissionOf($payment)),
            'legacyPayments' => $legacy->sortByDesc('paid_at')->values(),
        ]);
    }

    public function recordCollection(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'reference' => ['required', 'string', 'max:120'],
            'payment_id' => ['nullable', 'integer'],
        ], [
            'reference.required' => 'Indica la referencia del cobro.',
            'period_end.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        if ($data['payment_id'] ?? null) {
            $this->guardLegacyCommission(Payment::query()->find($data['payment_id']), $organization);
        }

        $pending = $this->sales->ticketPayments()
            ->whereBetween('paid_at', [Carbon::parse($data['period_start'])->startOfDay(), Carbon::parse($data['period_end'])->endOfDay()])
            ->whereIn('commission_status', ['pending', 'invoiced'])
            ->when($data['payment_id'] ?? null, fn (Builder $query, int $paymentId) => $query->whereKey($paymentId))
            ->where(function (Builder $query) use ($organization): void {
                $query->whereHas('reservation.departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organization->id))
                    ->orWhereHas('reservation.airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organization->id));
            })
            ->get();
        $pending = $pending->reject(fn (Payment $payment): bool => $this->sales->usesPassengerFee($payment))
            ->filter(fn (Payment $payment): bool => (float) $payment->commission_amount > 0 && $payment->commission_status !== 'voided');
        if ($pending->isEmpty()) {
            throw ValidationException::withMessages([
                'payment_id' => 'No hay comisiones anteriores pendientes en el período seleccionado.',
            ]);
        }

        foreach ($pending as $payment) {
            $payment->update([
                'commission_status' => 'paid',
                'commission_paid_at' => now(),
                'commission_reference' => $data['reference'],
            ]);
        }
        AuditRecorder::record($request, 'legacy_commission_collected', $organization, [
            'payment_ids' => $pending->pluck('id')->all(),
            'reference' => $data['reference'],
        ]);

        return back()->with('success', 'Se marcó la comisión anterior como cobrada. Los importes guardados no cambiaron.');
    }

    public function voidLegacy(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'payment_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'payment_id.required' => 'Elige la comisión anterior que vas a anular.',
            'reason.required' => 'Indica el motivo de la anulación.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);
        $payment = $this->guardLegacyCommission(Payment::query()->find($data['payment_id']), $organization);
        if ($payment->commission_status === 'paid') {
            throw ValidationException::withMessages([
                'payment_id' => 'Esa comisión anterior ya fue cobrada. No se anula.',
            ]);
        }
        $payment->update(['commission_status' => 'voided']);
        AuditRecorder::record($request, 'legacy_commission_voided', $payment, [
            'organization_id' => $organization->id,
            'commission_amount' => (float) $payment->commission_amount,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'La comisión anterior quedó anulada. El importe no se borró y ya no suma en el reporte.');
    }

    private function guardLegacyCommission(?Payment $payment, Organization $organization): Payment
    {
        if ($payment === null) {
            throw ValidationException::withMessages([
                'payment_id' => 'No se encontró esa comisión.',
            ]);
        }
        $payment->loadMissing(['reservation.departure.transportRoute', 'reservation.airDeparture.airRoute']);
        if ($this->organizationId($payment) !== $organization->id) {
            abort(404);
        }
        if ($this->sales->usesPassengerFee($payment)) {
            throw ValidationException::withMessages([
                'payment_id' => 'Esta venta no tiene una comisión por cobrar. El cargo de servicio ya lo pagó el viajero.',
            ]);
        }
        if ((float) $payment->commission_amount <= 0 || $payment->commission_status === 'voided') {
            throw ValidationException::withMessages([
                'payment_id' => 'Esa comisión anterior ya no se puede cobrar ni anular.',
            ]);
        }

        return $payment;
    }

    private function organizationId(Payment $payment): ?int
    {
        return $payment->reservation?->departure?->transportRoute?->organization_id
            ?? $payment->reservation?->airDeparture?->airRoute?->organization_id;
    }
}
