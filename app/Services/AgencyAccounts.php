<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencyLot;
use App\Models\AgencyLotPayment;
use App\Models\AgencyLotRelease;
use App\Models\Organization;
use App\Models\ReservationSeat;
use App\Models\RouteDepartureSeatHold;
use App\Support\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyAccounts
{
    /**
     * @return array{agencies: list<array<string, mixed>>, totals: array{lots: int, amount_cents: int, paid_cents: int, balance_cents: int, overdue_cents: int}}
     */
    public function statement(Organization $organization, ?int $agencyId = null): array
    {
        $agencies = Agency::query()
            ->where('organization_id', $organization->id)
            ->when($agencyId !== null, fn ($query) => $query->whereKey($agencyId))
            ->with([
                'lots' => fn ($query) => $query->withCount(['holds', 'releases'])->with([
                    'payments' => fn ($payments) => $payments->latest('id'),
                    'departure.transportRoute.originPort',
                    'departure.transportRoute.destinationPort',
                ])->latest('id'),
            ])
            ->orderBy('name')
            ->get();
        $lotIds = $agencies->flatMap(fn (Agency $agency) => $agency->lots->pluck('id'))->all();
        $sold = $this->soldCounts($lotIds);
        $rows = [];
        $totals = ['lots' => 0, 'amount_cents' => 0, 'paid_cents' => 0, 'balance_cents' => 0, 'overdue_cents' => 0];
        foreach ($agencies as $agency) {
            $lots = [];
            foreach ($agency->lots as $lot) {
                $quote = $this->quote($lot, (int) ($sold[$lot->id] ?? 0), $lot->payments, (int) $lot->holds_count, (int) $lot->releases_count);
                $lots[] = ['lot' => $lot, 'quote' => $quote];
                $totals['lots']++;
                $totals['amount_cents'] += $quote['amount_cents'];
                $totals['paid_cents'] += $quote['paid_cents'];
                $totals['balance_cents'] += $quote['balance_cents'];
                if ($quote['overdue']) {
                    $totals['overdue_cents'] += $quote['balance_cents'];
                }
            }
            if ($lots !== []) {
                $rows[] = ['agency' => $agency, 'lots' => $lots];
            }
        }

        return ['agencies' => $rows, 'totals' => $totals];
    }

    public function record(Request $request, AgencyLot $lot, string $amount, string $paidOn, string $method, string $operation, ?string $note): AgencyLotPayment
    {
        $cents = $this->cents($amount);
        if ($cents < 1) {
            throw ValidationException::withMessages(['amount' => __('agency_accounts.amount_required')]);
        }

        return DB::transaction(function () use ($request, $lot, $cents, $paidOn, $method, $operation, $note): AgencyLotPayment {
            $locked = AgencyLot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
            $payments = AgencyLotPayment::query()->where('agency_lot_id', $locked->id)->lockForUpdate()->get();
            $quote = $this->freshQuote($locked, $payments);
            if ($cents > $quote['balance_cents']) {
                throw ValidationException::withMessages(['amount' => __('agency_accounts.above_balance')]);
            }
            $organizationId = (int) $locked->agency()->value('organization_id');
            $payment = AgencyLotPayment::query()->create([
                'organization_id' => $organizationId,
                'agency_lot_id' => $locked->id,
                'amount' => $this->decimal($cents),
                'paid_on' => $paidOn,
                'method' => $method,
                'operation_number' => $operation,
                'note' => blank($note) ? null : trim((string) $note),
                'status' => 'recorded',
                'recorded_by_user_id' => $request->user()?->id,
            ]);
            AuditRecorder::record($request, 'lot_payment.recorded', $payment, [
                'agency' => (string) $locked->agency()->value('name'),
                'amount' => $this->decimal($cents),
                'operation' => $operation,
            ], $organizationId);

            return $payment;
        });
    }

    public function void(Request $request, AgencyLotPayment $payment, string $reason): void
    {
        DB::transaction(function () use ($request, $payment, $reason): void {
            $locked = AgencyLotPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'voided') {
                throw ValidationException::withMessages(['reason' => __('agency_accounts.already_voided')]);
            }
            $locked->update([
                'status' => 'voided',
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by_user_id' => $request->user()?->id,
            ]);
            $locked->load('lot.agency');
            AuditRecorder::record($request, 'lot_payment.voided', $locked, [
                'agency' => (string) $locked->lot?->agency?->name,
                'amount' => (string) $locked->amount,
                'operation' => $locked->operation_number,
                'reason' => $reason,
            ], $locked->organization_id);
        });
    }

    /**
     * @param  Collection<int, AgencyLotPayment>  $payments
     * @return array{assigned: int, sold: int, released: int, free: int, amount_cents: int, paid_cents: int, balance_cents: int, status: string, legacy: bool, overdue: bool}
     */
    public function quote(AgencyLot $lot, int $sold, Collection $payments, int $free, int $released): array
    {
        $assigned = $free + $released + $sold;
        $amount = $this->cents((string) $lot->unit_price) * ($free + $sold);
        $paid = 0;
        foreach ($payments as $payment) {
            if ($payment->status === 'recorded') {
                $paid += $this->cents((string) $payment->amount);
            }
        }
        $legacy = $lot->payment_status === 'paid' && $paid === 0;
        if ($legacy) {
            $paid = $amount;
        }
        $balance = $amount - $paid;
        $status = $paid === 0 ? 'pending' : ($balance <= 0 ? 'paid' : 'partial');
        $sailed = $lot->departure?->departure_at !== null && $lot->departure->departure_at->lte(now());

        return [
            'assigned' => $assigned,
            'sold' => $sold,
            'released' => $released,
            'free' => $free,
            'amount_cents' => $amount,
            'paid_cents' => $paid,
            'balance_cents' => $balance,
            'status' => $status,
            'legacy' => $legacy,
            'overdue' => $balance > 0 && $sailed,
        ];
    }

    public function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public function soles(int $cents): string
    {
        $prefix = $cents < 0 ? '−' : '';

        return $prefix.'S/ '.number_format(abs($cents) / 100, 2, '.', '');
    }

    /**
     * @param  Collection<int, AgencyLotPayment>  $payments
     * @return array{assigned: int, sold: int, released: int, free: int, amount_cents: int, paid_cents: int, balance_cents: int, status: string, legacy: bool, overdue: bool}
     */
    private function freshQuote(AgencyLot $lot, Collection $payments): array
    {
        $lot->loadMissing('departure');
        $free = RouteDepartureSeatHold::query()->where('agency_lot_id', $lot->id)->count();
        $released = AgencyLotRelease::query()->where('agency_lot_id', $lot->id)->count();
        $sold = (int) ($this->soldCounts([$lot->id])[$lot->id] ?? 0);

        return $this->quote($lot, $sold, $payments, $free, $released);
    }

    /**
     * @param  list<int>  $lotIds
     * @return array<int, int>
     */
    private function soldCounts(array $lotIds): array
    {
        if ($lotIds === []) {
            return [];
        }

        return ReservationSeat::query()
            ->join('reservations', 'reservations.id', '=', 'reservation_seats.reservation_id')
            ->whereIn('reservations.agency_lot_id', $lotIds)
            ->where('reservations.status', 'confirmed')
            ->groupBy('reservations.agency_lot_id')
            ->selectRaw('reservations.agency_lot_id as lot_id, count(*) as sold')
            ->pluck('sold', 'lot_id')
            ->map(fn ($sold): int => (int) $sold)
            ->all();
    }
}
