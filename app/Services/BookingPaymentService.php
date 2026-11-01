<?php

namespace App\Services;

use App\Mail\TicketConfirmedMail;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingPaymentService
{
    public function __construct(private readonly MarketplaceSales $sales) {}

    public function confirm(Reservation $reservation, array $paymentData, ?float $commissionRate = null): Reservation
    {
        $confirmed = DB::transaction(function () use ($reservation, $paymentData, $commissionRate) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($reservation->status === 'confirmed') {
                return $reservation;
            }
            abort_unless($reservation->status === 'pending_payment' && ! $reservation->expires_at?->isPast(), 422);

            $reservation->loadMissing(['departure.transportRoute.organization', 'airDeparture.airRoute.organization', 'seats']);
            $organization = $reservation->isAir()
                ? $reservation->airDeparture->airRoute->organization
                : $reservation->departure->transportRoute->organization;
            $quote = $this->sales->quote($reservation);
            $rate = $commissionRate ?? (float) ($organization->commission_rate ?? 0);
            $commission = $this->sales->commission($quote['charged_amount'], $rate);

            Payment::updateOrCreate(
                ['provider_reference' => $paymentData['provider_reference']],
                [
                    'reservation_id' => $reservation->id,
                    'checkout_order_id' => $reservation->checkout_order_id,
                    'purpose' => 'ticket',
                    'method' => $paymentData['method'],
                    'provider' => $paymentData['provider'],
                    'webhook_event_id' => $paymentData['webhook_event_id'] ?? null,
                    'amount' => $quote['charged_amount'],
                    'currency_code' => 'PEN',
                    'commission_rate' => $commission['commission_rate'],
                    'commission_amount' => $commission['commission_amount'],
                    'operator_net' => $commission['operator_net'],
                    'commission_status' => 'pending',
                    'status' => 'confirmed',
                    'paid_at' => now(),
                    'provider_payload' => array_merge($paymentData['provider_payload'] ?? [], [
                        'base_amount' => $quote['base_amount'],
                        'discount_amount' => $quote['discount_amount'],
                        'service_fee_amount' => $quote['service_fee_amount'],
                    ]),
                ],
            );

            $reservation->update([
                'base_amount' => $quote['base_amount'],
                'discount_amount' => $quote['discount_amount'],
                'service_fee_amount' => $quote['service_fee_amount'],
                'total_amount' => $quote['charged_amount'],
                'status' => 'confirmed',
                'payment_method' => $paymentData['method'],
                'paid_at' => now(),
                'expires_at' => null,
            ]);
            $reservation->seats->each(fn (ReservationSeat $seat) => Ticket::firstOrCreate(
                ['reservation_seat_id' => $seat->id],
                [
                    'code' => 'BOL-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                    'boarding_token' => Str::upper(Str::random(40)),
                    'status' => 'confirmed',
                    'issued_at' => now(),
                ],
            ));

            return $reservation->refresh();
        });

        $this->sendConfirmation($confirmed);

        return $confirmed;
    }

    private function sendConfirmation(Reservation $reservation): void
    {
        if (! filter_var($reservation->contact_email, FILTER_VALIDATE_EMAIL)
            || str_ends_with($reservation->contact_email, '@pos.navegaya.test')) {
            return;
        }
        try {
            Mail::to($reservation->contact_email)->send(new TicketConfirmedMail($reservation));
        } catch (\Throwable $exception) {
            logger()->warning('No se pudo enviar el correo del boleto.', ['reservation' => $reservation->code, 'error' => $exception->getMessage()]);
        }
    }
}
