<?php

namespace App\Services;

use App\Models\OperatorPayout;
use App\Models\OperatorPayoutItem;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\WebFareRefund;
use App\Support\AuditRecorder;
use App\Support\DepartureSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyCollections
{
    /**
     * @return array{
     *     view: string,
     *     date: string,
     *     from: string,
     *     to: string,
     *     label: string,
     *     start: Carbon,
     *     end: Carbon
     * }
     */
    public function period(string $view, string $date): array
    {
        if (! in_array($view, ['semana', 'mes'], true)) {
            $view = 'mes';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = now()->timezone(DepartureSchedule::ZONE)->toDateString();
        }
        $anchor = Carbon::parse($date, DepartureSchedule::ZONE);
        if ($view === 'semana') {
            $start = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $anchor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            $label = __('collections.week_of', ['from' => $start->format('d/m/Y'), 'to' => $end->format('d/m/Y')]);
        } else {
            $start = $anchor->copy()->startOfMonth()->startOfDay();
            $end = $anchor->copy()->endOfMonth()->endOfDay();
            $label = ucfirst($anchor->locale('es')->translatedFormat('F Y'));
        }

        return [
            'view' => $view,
            'date' => $anchor->toDateString(),
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'label' => $label,
            'start' => $start->copy()->utc(),
            'end' => $end->copy()->utc(),
        ];
    }

    /**
     * @param  array{view: string, date: string, from: string, to: string, label: string, start: Carbon, end: Carbon}  $period
     * @return array<string, mixed>
     */
    public function statement(Organization $organization, array $period): array
    {
        $sales = $this->sales($organization)->with('reservation')->orderBy('paid_at')->orderBy('id')->get();
        $refunds = WebFareRefund::query()->where('organization_id', $organization->id)->with('payment.reservation')->orderBy('refunded_at')->get();
        $payouts = OperatorPayout::query()
            ->where('organization_id', $organization->id)
            ->with(['items.payment.reservation', 'recorder'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get();

        $opening = $this->balance($sales, $refunds, $payouts, $period['start']->copy()->subSecond(), $this->dayBefore($period['from']));
        $closing = $this->balance($sales, $refunds, $payouts, $period['end'], $period['to']);
        $inPeriod = $sales->filter(fn (Payment $payment): bool => $payment->paid_at !== null && $payment->paid_at->between($period['start'], $period['end']));
        $periodRefunds = $refunds->filter(fn (WebFareRefund $refund): bool => $refund->refunded_at !== null && $refund->refunded_at->between($period['start'], $period['end']));
        $periodPayouts = $payouts->filter(function (OperatorPayout $payout) use ($period): bool {
            $day = $payout->paid_on?->toDateString();

            return $day !== null && $day >= $period['from'] && $day <= $period['to'];
        });
        $paidPayouts = $periodPayouts->filter(fn (OperatorPayout $payout): bool => $payout->status === 'paid');

        return [
            'opening_cents' => $opening,
            'sales_count' => $inPeriod->count(),
            'fare_cents' => $inPeriod->sum(fn (Payment $payment): int => $this->fareCents($payment)),
            'discount_cents' => $inPeriod->sum(fn (Payment $payment): int => $this->discountCents($payment)),
            'fee_cents' => $inPeriod->sum(fn (Payment $payment): int => $this->cents((string) $payment->commission_amount)),
            'net_cents' => $inPeriod->sum(fn (Payment $payment): int => $this->cents((string) $payment->operator_net)),
            'refund_cents' => $periodRefunds->sum(fn (WebFareRefund $refund): int => $this->cents((string) $refund->amount)),
            'paid_cents' => $paidPayouts->sum(fn (OperatorPayout $payout): int => $this->cents((string) $payout->amount)),
            'pending_cents' => $closing,
            'sales' => $inPeriod->map(fn (Payment $payment): array => $this->saleRow($payment, $refunds, $payouts))->values(),
            'refunds' => $periodRefunds->map(fn (WebFareRefund $refund): array => [
                'code' => $refund->payment?->reservation?->code ?? '',
                'passenger' => $refund->payment?->reservation?->contact_name ?? '',
                'amount_cents' => $this->cents((string) $refund->amount),
                'when' => $refund->refunded_at,
                'reason' => $refund->reason,
            ])->values(),
            'payouts' => $periodPayouts->map(fn (OperatorPayout $payout): array => [
                'id' => $payout->id,
                'amount_cents' => $this->cents((string) $payout->amount),
                'method' => $payout->method,
                'operation' => $payout->operation_number,
                'note' => $payout->note,
                'status' => $payout->status,
                'reason' => $payout->void_reason,
                'when' => $payout->paid_on,
                'tickets' => $payout->items->map(fn (OperatorPayoutItem $item): array => [
                    'code' => $item->payment?->reservation?->code ?? '',
                    'passenger' => $item->payment?->reservation?->contact_name ?? '',
                    'amount_cents' => $this->cents((string) $item->amount),
                ])->all(),
            ])->values(),
        ];
    }

    public function recordPayout(Request $request, Organization $organization, string $paidOn, string $amount, string $method, string $operation, ?string $note): OperatorPayout
    {
        $cents = $this->cents($amount);
        if ($cents < 1) {
            throw ValidationException::withMessages(['amount' => __('collections.amount_required')]);
        }

        $payout = DB::transaction(function () use ($request, $organization, $paidOn, $cents, $method, $operation, $note): OperatorPayout {
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();
            $sales = $this->sales($organization)->orderBy('paid_at')->orderBy('id')->lockForUpdate()->get();
            OperatorPayout::query()->where('organization_id', $organization->id)->lockForUpdate()->get();
            $refunds = WebFareRefund::query()->where('organization_id', $organization->id)->lockForUpdate()->get();
            $payouts = OperatorPayout::query()->where('organization_id', $organization->id)->where('status', 'paid')->with('items')->get();
            // El saldo a favor de NavegaYA (devoluciones de boletos ya pagados) se descuenta antes de pagar.
            if ($cents > $this->balance($sales, $refunds, $payouts, now()->addYear(), '2999-12-31')) {
                throw ValidationException::withMessages(['amount' => __('collections.above_balance')]);
            }
            $remaining = $cents;
            $allocations = [];
            foreach ($sales as $sale) {
                if ($remaining < 1) {
                    break;
                }
                $open = $this->openCents($sale, $refunds, $payouts);
                if ($open < 1) {
                    continue;
                }
                $take = min($open, $remaining);
                $allocations[] = ['payment_id' => $sale->id, 'amount' => $this->decimal($take)];
                $remaining -= $take;
            }
            if ($remaining > 0) {
                throw ValidationException::withMessages(['amount' => __('collections.above_balance')]);
            }

            $payout = OperatorPayout::query()->create([
                'organization_id' => $organization->id,
                'paid_on' => $paidOn,
                'amount' => $this->decimal($cents),
                'method' => $method,
                'operation_number' => $operation,
                'note' => $note,
                'status' => 'paid',
                'recorded_by_user_id' => $request->user()?->id,
            ]);
            foreach ($allocations as $allocation) {
                $payout->items()->create($allocation);
            }

            AuditRecorder::record($request, 'payout.recorded', $payout, [
                'amount' => (string) $payout->amount,
                'operation' => $payout->operation_number,
                'method' => $payout->method,
            ], $organization->id);

            return $payout;
        });

        return $payout;
    }

    public function voidPayout(Request $request, OperatorPayout $payout, string $reason): void
    {
        DB::transaction(function () use ($request, $payout, $reason): void {
            $locked = OperatorPayout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'voided') {
                throw ValidationException::withMessages(['reason' => __('collections.already_voided')]);
            }
            $locked->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by_user_id' => $request->user()?->id,
                'void_reason' => $reason,
            ]);
            AuditRecorder::record($request, 'payout.voided', $locked, [
                'amount' => (string) $locked->amount,
                'operation' => $locked->operation_number,
                'reason' => $reason,
            ], $locked->organization_id);
        });
    }

    public function recordRefund(Request $request, Payment $payment, string $amount, string $reason): WebFareRefund
    {
        $cents = $this->cents($amount);
        if ($cents < 1) {
            throw ValidationException::withMessages(['amount' => __('collections.amount_required')]);
        }

        return DB::transaction(function () use ($request, $payment, $cents, $reason): WebFareRefund {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('reservation.departure.transportRoute', 'reservation.airDeparture.airRoute');
            $organization = app(MarketplaceSales::class)->organization($locked);
            if ($organization === null || ! $this->isWebFare($locked)) {
                throw ValidationException::withMessages(['payment' => __('collections.not_web_fare')]);
            }
            $already = (int) WebFareRefund::query()->where('payment_id', $locked->id)->lockForUpdate()->get()
                ->sum(fn (WebFareRefund $refund): int => $this->cents((string) $refund->amount));
            if ($cents > $this->cents((string) $locked->operator_net) - $already) {
                throw ValidationException::withMessages(['amount' => __('collections.refund_above_fare')]);
            }
            $refund = WebFareRefund::query()->create([
                'organization_id' => $organization->id,
                'payment_id' => $locked->id,
                'amount' => $this->decimal($cents),
                'reason' => $reason,
                'refunded_at' => now(),
                'recorded_by_user_id' => $request->user()?->id,
            ]);
            AuditRecorder::record($request, 'web_refund.recorded', $refund, [
                'code' => $locked->reservation?->code,
                'amount' => $this->decimal($cents),
                'reason' => $reason,
            ], $organization->id);

            return $refund;
        });
    }

    public function soles(int $cents): string
    {
        return 'S/ '.number_format(abs($cents) / 100, 2, '.', '');
    }

    public function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * @return Builder<Payment>
     */
    private function sales(Organization $organization): Builder
    {
        return Payment::query()
            ->whereIn('status', ['confirmed', 'succeeded'])
            ->where(fn (Builder $query) => $query->whereNull('purpose')->orWhere('purpose', 'ticket'))
            ->where('provider_payload->commission_model', 'passenger_fee')
            ->whereHas('reservation', function (Builder $reservation) use ($organization): void {
                $reservation->where('sales_channel', 'web')->where(function (Builder $channel) use ($organization): void {
                    $channel->whereHas('departure.transportRoute', fn (Builder $route) => $route->where('organization_id', $organization->id))
                        ->orWhereHas('airDeparture.airRoute', fn (Builder $route) => $route->where('organization_id', $organization->id));
                });
            });
    }

    private function isWebFare(Payment $payment): bool
    {
        return in_array($payment->status, ['confirmed', 'succeeded'], true)
            && ($payment->purpose === null || $payment->purpose === 'ticket')
            && data_get($payment->provider_payload, 'commission_model') === 'passenger_fee'
            && $payment->reservation?->sales_channel === 'web';
    }

    /**
     * @param  Collection<int, Payment>  $sales
     * @param  Collection<int, WebFareRefund>  $refunds
     * @param  Collection<int, OperatorPayout>  $payouts
     */
    private function balance(Collection $sales, Collection $refunds, Collection $payouts, Carbon $until, string $untilDate): int
    {
        $open = 0;
        $credit = 0;
        foreach ($sales as $sale) {
            if ($sale->paid_at === null || $sale->paid_at->greaterThan($until)) {
                continue;
            }
            [$ticketOpen, $ticketCredit] = $this->split($sale, $refunds, $payouts, $until, $untilDate);
            $open += $ticketOpen;
            $credit += $ticketCredit;
        }

        return $open - $credit;
    }

    /**
     * @param  Collection<int, WebFareRefund>  $refunds
     * @param  Collection<int, OperatorPayout>  $payouts
     * @return array{0: int, 1: int}
     */
    private function split(Payment $sale, Collection $refunds, Collection $payouts, Carbon $until, string $untilDate): array
    {
        $net = $this->cents((string) $sale->operator_net);
        $refunded = $refunds
            ->filter(fn (WebFareRefund $refund): bool => (int) $refund->payment_id === (int) $sale->id && $refund->refunded_at !== null && ! $refund->refunded_at->greaterThan($until))
            ->sum(fn (WebFareRefund $refund): int => $this->cents((string) $refund->amount));
        $paid = 0;
        foreach ($payouts as $payout) {
            if ($payout->status !== 'paid') {
                continue;
            }
            $day = $payout->paid_on?->toDateString();
            if ($day === null || $day > $untilDate) {
                continue;
            }
            foreach ($payout->items as $item) {
                if ((int) $item->payment_id === (int) $sale->id) {
                    $paid += $this->cents((string) $item->amount);
                }
            }
        }
        $stillOpen = max(0, $net - $paid);

        return [max(0, $stillOpen - $refunded), max(0, $refunded - $stillOpen)];
    }

    /**
     * @param  Collection<int, WebFareRefund>  $refunds
     * @param  Collection<int, OperatorPayout>  $payouts
     */
    private function openCents(Payment $sale, Collection $refunds, Collection $payouts): int
    {
        [$open] = $this->split($sale, $refunds, $payouts, now()->addYear(), '2999-12-31');

        return $open;
    }

    /**
     * @param  Collection<int, WebFareRefund>  $refunds
     * @param  Collection<int, OperatorPayout>  $payouts
     * @return array<string, mixed>
     */
    private function saleRow(Payment $payment, Collection $refunds, Collection $payouts): array
    {
        $net = $this->cents((string) $payment->operator_net);
        $paid = 0;
        foreach ($payouts as $payout) {
            if ($payout->status !== 'paid') {
                continue;
            }
            foreach ($payout->items as $item) {
                if ((int) $item->payment_id === (int) $payment->id) {
                    $paid += $this->cents((string) $item->amount);
                }
            }
        }
        $refunded = $refunds->where('payment_id', $payment->id)->sum(fn (WebFareRefund $refund): int => $this->cents((string) $refund->amount));
        $state = 'pending';
        if ($refunded >= $net && $net > 0) {
            $state = 'refunded';
        } elseif ($paid >= max(0, $net - $refunded) && $paid > 0) {
            $state = 'paid';
        } elseif ($paid > 0) {
            $state = 'partial';
        }

        return [
            'code' => $payment->reservation?->code ?? '',
            'passenger' => $payment->reservation?->contact_name ?? '',
            'when' => $payment->paid_at,
            'fare_cents' => $this->fareCents($payment),
            'discount_cents' => $this->discountCents($payment),
            'fee_cents' => $this->cents((string) $payment->commission_amount),
            'net_cents' => $net,
            'state' => $state,
        ];
    }

    private function fareCents(Payment $payment): int
    {
        if ($payment->reservation?->base_amount !== null) {
            return $this->cents((string) $payment->reservation->base_amount);
        }

        return $this->cents((string) $payment->operator_net) + $this->discountCents($payment);
    }

    private function discountCents(Payment $payment): int
    {
        return $this->cents((string) ($payment->reservation?->discount_amount ?? 0));
    }

    public function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function dayBefore(string $date): string
    {
        return Carbon::parse($date, DepartureSchedule::ZONE)->subDay()->toDateString();
    }
}
