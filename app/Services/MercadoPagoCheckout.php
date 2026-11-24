<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MercadoPagoCheckout
{
    public function __construct(
        private readonly MercadoPagoGateway $mercadoPago,
        private readonly BookingPaymentService $payments,
    ) {}

    public function settle(string $paymentId, ?string $eventId, ?Reservation $expected = null): string
    {
        $remote = $this->mercadoPago->retrievePayment($paymentId);
        if (($remote['status'] ?? null) !== 'approved' || ($remote['currency_id'] ?? null) !== 'PEN') {
            return 'pending';
        }
        $reference = $this->mercadoPago->reference($paymentId);
        $charged = $this->mercadoPago->centsFromMixed($remote['transaction_amount'] ?? null);
        $code = $remote['external_reference'] ?? null;
        if ($charged === null || ! is_string($code) || ($expected !== null && $code !== $expected->code)) {
            return 'ignored';
        }
        $existing = Payment::query()->where('provider_reference', $reference)->first();
        if ($existing && in_array($existing->status, ['confirmed', 'succeeded', 'refunded', 'review_required'], true)) {
            return 'duplicate';
        }
        $reservation = Reservation::query()->where('code', $code)->first();
        if ($reservation === null) {
            return 'ignored';
        }
        $expectedCents = $this->mercadoPago->ticketCents($reservation);
        if ($charged !== $expectedCents || $reservation->status === 'confirmed' || ! $this->payable($reservation)) {
            $this->mercadoPago->release($reservation, $reference, $charged);

            return 'returned';
        }
        $wasConfirmed = false;
        try {
            $confirmed = $this->payments->confirm($reservation, [
                'method' => $this->method($remote),
                'provider' => 'mercadopago',
                'provider_reference' => $reference,
                'webhook_event_id' => $this->eventId($eventId),
                'provider_payload' => $this->mercadoPago->safePayload($remote),
            ]);
        } catch (HttpExceptionInterface) {
            $this->mercadoPago->release($reservation->fresh() ?? $reservation, $reference, $charged);

            return 'returned';
        }
        $payment = Payment::query()->where('provider_reference', $reference)->first();
        $settled = $confirmed->status === 'confirmed'
            && $payment !== null
            && (int) $payment->reservation_id === (int) $reservation->id
            && in_array($payment->status, ['confirmed', 'succeeded'], true);
        if (! $settled) {
            $this->mercadoPago->release($reservation->fresh() ?? $reservation, $reference, $charged);

            return 'returned';
        }
        if (! $wasConfirmed) {
            $this->mercadoPago->recordConfirmation($confirmed, $payment);
        }

        return 'confirmed';
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function method(array $remote): string
    {
        $type = $remote['payment_type_id'] ?? null;

        return in_array($type, ['credit_card', 'debit_card', 'prepaid_card'], true) ? 'card' : 'wallet';
    }

    private function eventId(?string $eventId): ?string
    {
        if ($eventId === null || preg_match('/^[A-Za-z0-9-]{1,80}$/', $eventId) !== 1) {
            return null;
        }

        return $eventId;
    }

    private function payable(Reservation $reservation): bool
    {
        return $reservation->status === 'pending_payment' && ! $reservation->expires_at?->isPast();
    }
}
