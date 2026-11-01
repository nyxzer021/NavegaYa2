<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SystemSetting;
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
            : (float) SystemSetting::value('platform_fee_percent', '10');
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
     * @return array{commission_rate: float, commission_amount: float, operator_net: float}
     */
    public function commission(float $charged, ?float $rate): array
    {
        $rate = round((float) ($rate ?? 0), 2);
        $commission = round($charged * $rate / 100, 2);

        return [
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'operator_net' => round($charged - $commission, 2),
        ];
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

    public function commissionOf(Payment $payment): float
    {
        if ((float) $payment->commission_amount > 0 || (float) $payment->commission_rate > 0) {
            return round((float) $payment->commission_amount, 2);
        }

        if ($payment->provider === 'pos' || $payment->purpose === 'subscription' || $payment->reservation_id === null) {
            return 0.0;
        }

        $rate = (float) ($this->organization($payment)?->commission_rate ?? 0);

        return round((float) $payment->amount * $rate / 100, 2);
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
