<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceSales
{
    /**
     * GMV is money collected for tickets. Subscription and advertising payments are excluded.
     *
     * @return Builder<Payment>
     */
    public function ticketPayments(): Builder
    {
        return Payment::query()
            ->whereIn('status', ['confirmed', 'succeeded'])
            ->whereNotNull('reservation_id')
            ->where(function (Builder $query): void {
                $query->whereNull('purpose')->orWhere('purpose', 'ticket');
            });
    }

    /**
     * @return array{base_amount: float, discount_amount: float, service_fee_percent: float, service_fee_amount: float, charged_amount: float}
     */
    public function quote(Reservation $reservation): array
    {
        $base = round((float) ($reservation->base_amount ?? $reservation->total_amount), 2);
        $discount = round((float) ($reservation->discount_amount ?? 0), 2);
        $net = round(max(0, $base - $discount), 2);
        $percent = $reservation->sales_channel === 'counter'
            ? 0.0
            : $this->passengerFeePercent($reservation);
        $fee = round($net * $percent / 100, 2);

        return [
            'base_amount' => $base,
            'discount_amount' => $discount,
            'service_fee_percent' => $percent,
            'service_fee_amount' => $fee,
            'charged_amount' => round($net + $fee, 2),
        ];
    }

    /**
     * NavegaYA solo registra el cargo de servicio que ya pagó el pasajero.
     * commission_amount es service_fee_amount. La empresa recibe
     * operator_net = base_amount − discount_amount, sin otro descuento.
     *
     * @param  array{base_amount: float|int, discount_amount: float|int, service_fee_percent: float|int, service_fee_amount: float|int}  $quote
     * @return array{commission_rate: float, commission_amount: float, operator_net: float}
     */
    public function commission(array $quote): array
    {
        return [
            'commission_rate' => round((float) $quote['service_fee_percent'], 2),
            'commission_amount' => round((float) $quote['service_fee_amount'], 2),
            'operator_net' => round(max(0, (float) $quote['base_amount'] - (float) $quote['discount_amount']), 2),
        ];
    }

    public function passengerFeePercent(Reservation $reservation): float
    {
        $reservation->loadMissing(['departure.transportRoute.organization', 'airDeparture.airRoute.organization']);
        $organization = $reservation->departure?->transportRoute?->organization
            ?? $reservation->airDeparture?->airRoute?->organization;

        return round((float) ($organization?->commission_rate ?? 0), 2);
    }

    public function charged(Reservation $reservation): float
    {
        if ($reservation->base_amount !== null && $reservation->status === 'confirmed') {
            return round((float) $reservation->base_amount - (float) $reservation->discount_amount + (float) $reservation->service_fee_amount, 2);
        }

        $payment = $this->ticketPayment($reservation);
        if ($payment instanceof Payment && $this->paymentCoversOnly($payment, $reservation)) {
            return round((float) $payment->amount, 2);
        }

        return round((float) $reservation->total_amount, 2);
    }

    public function perSeatCharged(Reservation $reservation): float
    {
        $seats = $reservation->relationLoaded('seats') ? $reservation->seats->count() : $reservation->seats()->count();

        return round($this->charged($reservation) / max(1, $seats), 2);
    }

    public function fareOf(Payment $payment): float
    {
        $payment->loadMissing('reservation');
        $reservation = $payment->reservation;
        if ($reservation?->base_amount !== null) {
            return round(max(0, (float) $reservation->base_amount - (float) $reservation->discount_amount), 2);
        }

        $fee = round((float) data_get($payment->provider_payload, 'service_fee_amount', 0), 2);

        return round(max(0, (float) $payment->amount - $fee), 2);
    }

    public function usesPassengerFee(Payment $payment): bool
    {
        return data_get($payment->provider_payload, 'commission_model') === 'passenger_fee';
    }

    /**
     * Importe guardado al confirmar. Las ventas nuevas guardan el cargo del pasajero;
     * las anteriores guardan el descuento que se aplicaba a la empresa. No se recalcula.
     */
    public function commissionOf(Payment $payment): float
    {
        return round((float) $payment->commission_amount, 2);
    }

    public function organization(Payment $payment): ?Organization
    {
        $payment->loadMissing(['reservation.departure.transportRoute.organization', 'reservation.airDeparture.airRoute.organization']);

        return $payment->reservation?->departure?->transportRoute?->organization
            ?? $payment->reservation?->airDeparture?->airRoute?->organization;
    }

    private function ticketPayment(Reservation $reservation): ?Payment
    {
        $payments = $reservation->relationLoaded('payments')
            ? $reservation->payments
            : $reservation->payments()->get();

        return $payments->first(fn (Payment $payment): bool => in_array($payment->status, ['confirmed', 'succeeded'], true)
            && ($payment->purpose === null || $payment->purpose === 'ticket'));
    }

    private function paymentCoversOnly(Payment $payment, Reservation $reservation): bool
    {
        if ((int) $payment->reservation_id !== (int) $reservation->id) {
            return false;
        }

        if (! $payment->checkout_order_id) {
            return true;
        }

        return Reservation::query()->where('checkout_order_id', $payment->checkout_order_id)->count() === 1;
    }
}
