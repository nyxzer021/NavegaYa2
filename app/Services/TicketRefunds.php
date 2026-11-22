<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Models\Ticket;
use App\Models\TicketRefund;
use App\Support\AuditRecorder;
use App\Support\RefundRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketRefunds
{
    public function __construct(
        private readonly RefundRule $rule,
        private readonly CompanyCollections $collections,
        private readonly CashDesk $cash,
    ) {}

    public function request(Request $request, Reservation $reservation, string $source, string $cause, ?string $amount, ?string $reason): TicketRefund
    {
        $reservation->loadMissing('departure.transportRoute', 'airDeparture.airRoute', 'seats.ticket', 'payments');
        $this->assertNotBoarded($reservation);
        $payment = $this->ticketPayment($reservation);
        $this->assertNoOpen($payment->id);
        $organization = $this->organization($reservation);
        $policy = $cause === RefundRule::PASSENGER ? $this->policyPercent($organization) : null;
        $quote = $this->rule->quote($payment, $cause, $policy, blank($amount) ? null : $amount);
        if ($source === 'company' && $quote['needs_amount']) {
            throw ValidationException::withMessages(['amount' => __('refunds.amount_required')]);
        }

        return DB::transaction(function () use ($request, $reservation, $payment, $organization, $source, $cause, $quote, $reason): TicketRefund {
            // Bloquea el pago: dos pedidos a la vez no pueden abrir dos devoluciones del mismo boleto.
            Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $this->assertNoOpen($payment->id);
            $refund = TicketRefund::query()->create([
                'organization_id' => $organization->id,
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
                'source' => $source,
                'cause' => $cause,
                'status' => 'requested',
                'fare_amount' => $this->collections->decimal($quote['fare_cents']),
                'fee_amount' => $this->collections->decimal($quote['fee_cents']),
                'amount' => $this->collections->decimal($quote['total_cents']),
                'policy_percent' => $quote['percent'],
                'explanation' => $quote['explanation'],
                'reason' => blank($reason) ? null : trim((string) $reason),
                'requested_by_user_id' => $request->user()?->id,
            ]);
            AuditRecorder::record($request, 'refund.requested', $refund, [
                'code' => $reservation->code,
                'amount' => $this->collections->decimal($quote['total_cents']),
                'reason' => (string) $reason,
            ], $organization->id);

            return $refund;
        });
    }

    public function approve(Request $request, TicketRefund $refund, ?string $amount): TicketRefund
    {
        return DB::transaction(function () use ($request, $refund, $amount): TicketRefund {
            $locked = TicketRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'requested') {
                throw ValidationException::withMessages(['refund' => __('refunds.not_requested')]);
            }
            $reservation = Reservation::query()->whereKey($locked->reservation_id)->lockForUpdate()->firstOrFail();
            $reservation->load('seats.ticket', 'departure', 'payments');
            $this->assertNotBoarded($reservation);
            $payment = Payment::query()->whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();
            $organization = Organization::query()->findOrFail($locked->organization_id);
            $policy = $locked->cause === RefundRule::COMPANY_CANCEL
                ? null
                : ($locked->policy_percent !== null ? (string) $locked->policy_percent : $this->policyPercent($organization));
            $typed = blank($amount) ? null : $amount;
            if ($typed === null && $policy === null && $locked->cause === RefundRule::PASSENGER && (int) round(((float) $locked->amount) * 100) > 0) {
                $typed = (string) $locked->amount;
            }
            $quote = $this->rule->quote($payment, $locked->cause, $policy, $typed);
            if ($quote['needs_amount'] || $quote['total_cents'] < 1) {
                throw ValidationException::withMessages(['amount' => __('refunds.amount_required')]);
            }
            $this->releaseSeat($reservation);
            $webId = null;
            if ($reservation->sales_channel === 'web' && $quote['fare_cents'] > 0) {
                $webId = $this->collections->recordRefund(
                    $request,
                    $payment,
                    $this->collections->decimal($quote['fare_cents']),
                    $locked->reason ?: $quote['explanation'],
                )->id;
            }
            $locked->update([
                'fare_amount' => $this->collections->decimal($quote['fare_cents']),
                'fee_amount' => $this->collections->decimal($quote['fee_cents']),
                'amount' => $this->collections->decimal($quote['total_cents']),
                'policy_percent' => $quote['percent'],
                'explanation' => $quote['explanation'],
                'status' => 'approved',
                'web_fare_refund_id' => $webId,
                'decided_by_user_id' => $request->user()?->id,
                'decided_at' => now(),
            ]);
            AuditRecorder::record($request, 'refund.approved', $locked, [
                'code' => $reservation->code,
                'amount' => $this->collections->decimal($quote['total_cents']),
                'reason' => (string) $locked->reason,
            ], $organization->id);

            return $locked->refresh();
        });
    }

    public function reject(Request $request, TicketRefund $refund, string $reason): void
    {
        DB::transaction(function () use ($request, $refund, $reason): void {
            $locked = TicketRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'requested') {
                throw ValidationException::withMessages(['refund' => __('refunds.not_requested')]);
            }
            $locked->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'decided_by_user_id' => $request->user()?->id,
                'decided_at' => now(),
            ]);
            AuditRecorder::record($request, 'refund.rejected', $locked, [
                'code' => $locked->reservation()->value('code'),
                'amount' => (string) $locked->amount,
                'reason' => $reason,
            ], $locked->organization_id);
        });
    }

    public function pay(Request $request, TicketRefund $refund, ?string $operation): void
    {
        DB::transaction(function () use ($request, $refund, $operation): void {
            $locked = TicketRefund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages(['refund' => __('refunds.not_approved')]);
            }
            $locked->load('reservation', 'payment');
            if ($locked->reservation?->sales_channel === 'web') {
                throw ValidationException::withMessages(['refund' => __('refunds.web_waits')]);
            }
            $user = $request->user();
            abort_unless($user !== null, 403);
            $method = (string) $locked->payment?->method;
            $cashId = null;
            $operationNumber = null;
            if ($locked->reservation?->sales_channel === 'agency') {
                // El dinero lo cobró la agencia: la devuelve ella y la caja de la empresa no se mueve.
                $operationNumber = trim((string) $operation) ?: null;
            } elseif ($method === 'cash') {
                $cashId = $this->cash->refundCash($user, $locked->payment, (string) $locked->amount, $locked->reason ?: $locked->explanation)->id;
            } else {
                $operationNumber = trim((string) $operation);
                if ($operationNumber === '') {
                    throw ValidationException::withMessages(['operation_number' => __('refunds.operation_required')]);
                }
            }
            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
                'cash_payment_id' => $cashId,
                'operation_number' => $operationNumber,
                'decided_by_user_id' => $locked->decided_by_user_id ?: $user->id,
            ]);
            AuditRecorder::record($request, 'refund.paid', $locked, [
                'code' => $locked->reservation?->code,
                'amount' => (string) $locked->amount,
                'reason' => (string) ($operationNumber ?: $locked->reason),
            ], $locked->organization_id);
        });
    }

    public function fromCancellation(Request $request, RouteDeparture $departure): void
    {
        $reservations = Reservation::query()
            ->where('route_departure_id', $departure->id)
            ->where('status', 'confirmed')
            ->with(['payments', 'seats.ticket', 'departure.transportRoute'])
            ->get();
        foreach ($reservations as $reservation) {
            try {
                $this->request($request, $reservation, 'departure_cancelled', RefundRule::COMPANY_CANCEL, null, __('refunds.cancel_reason'));
            } catch (ValidationException) {
                continue;
            }
        }
    }

    private function releaseSeat(Reservation $reservation): void
    {
        $reservation->loadMissing('departure');
        $sailed = $reservation->departure?->departure_at !== null && $reservation->departure->departure_at->lte(now());
        if ($sailed || $reservation->route_departure_id === null) {
            return;
        }
        $seatRows = $reservation->seats()->get();
        $seatIds = $seatRows->pluck('vessel_seat_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $reservation->seats()->update(['vessel_seat_id' => null]);
        if ($reservation->sales_channel === 'agency' && $reservation->agency_id && $reservation->agency_lot_id) {
            foreach ($seatIds as $seatId) {
                RouteDepartureSeatHold::query()->firstOrCreate(
                    ['route_departure_id' => $reservation->route_departure_id, 'vessel_seat_id' => $seatId],
                    ['kind' => 'agency', 'agency_id' => $reservation->agency_id, 'agency_lot_id' => $reservation->agency_lot_id],
                );
            }
        }
        Ticket::query()->whereIn('reservation_seat_id', $reservation->seats()->pluck('id'))->update(['status' => 'voided']);
    }

    private function assertNotBoarded(Reservation $reservation): void
    {
        $reservation->loadMissing('seats.ticket');
        $boarded = $reservation->seats->contains(function (ReservationSeat $seat): bool {
            return $seat->ticket?->status === 'boarded' || $seat->ticket?->boarded_at !== null;
        });
        if ($boarded) {
            throw ValidationException::withMessages(['code' => __('refunds.boarded')]);
        }
    }

    private function assertNoOpen(int $paymentId): void
    {
        $open = TicketRefund::query()
            ->where('payment_id', $paymentId)
            ->where('status', '!=', 'rejected')
            ->exists();
        if ($open) {
            throw ValidationException::withMessages(['code' => __('refunds.already')]);
        }
    }

    private function ticketPayment(Reservation $reservation): Payment
    {
        $payment = $reservation->payments
            ->first(fn (Payment $row): bool => in_array($row->status, ['confirmed', 'succeeded'], true)
                && ($row->purpose === null || $row->purpose === 'ticket')
                && (float) $row->amount > 0);
        if ($payment === null) {
            throw ValidationException::withMessages(['code' => __('refunds.no_payment')]);
        }

        return $payment;
    }

    private function organization(Reservation $reservation): Organization
    {
        $id = $reservation->departure?->transportRoute?->organization_id
            ?? $reservation->airDeparture?->airRoute?->organization_id;
        $organization = $id === null ? null : Organization::query()->find($id);
        if ($organization === null) {
            throw ValidationException::withMessages(['code' => __('refunds.no_payment')]);
        }

        return $organization;
    }

    private function policyPercent(Organization $organization): ?string
    {
        return $organization->refund_regret_percent === null ? null : (string) $organization->refund_regret_percent;
    }
}
