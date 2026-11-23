<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\BookingPaymentService;
use App\Services\CulqiException;
use App\Services\CulqiGateway;
use App\Services\SubscriptionPaymentService;
use App\Support\PaymentConnections;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PaymentWebhookController extends Controller
{
    public function culqi(Request $request, CulqiGateway $culqi, BookingPaymentService $payments, SubscriptionPaymentService $subscriptionPayments): JsonResponse
    {
        PaymentConnections::rememberCulqi();
        $raw = $request->getContent();
        if (! $culqi->signatureValid($raw, $request)) {
            Log::warning('Webhook de Culqi rechazado.', ['motivo' => 'firma']);

            return response()->json(['message' => 'Firma inválida.'], 401);
        }
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            Log::warning('Webhook de Culqi rechazado.', ['motivo' => 'cuerpo']);

            return response()->json(['message' => 'Cuerpo inválido.'], 400);
        }
        $event = (string) ($payload['type'] ?? '');
        $eventId = (string) ($payload['id'] ?? '');
        if ($eventId !== '' && Payment::query()->where('webhook_event_id', $eventId)->exists()) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }
        if ($event === 'order.status.changed') {
            return $this->handleOrder($payload, $eventId, $culqi, $payments, $subscriptionPayments);
        }
        if (! in_array($event, ['charge.succeeded', 'charge.creation.succeeded'], true)) {
            Log::info('Webhook de Culqi ignorado.', ['tipo' => $event]);

            return response()->json(['received' => true]);
        }
        $incoming = $this->resource($payload);
        $chargeId = (string) ($incoming['id'] ?? '');
        if ($chargeId === '') {
            return response()->json(['received' => true]);
        }
        try {
            $verified = $culqi->retrieveCharge($chargeId);
        } catch (CulqiException) {
            return response()->json(['message' => __('payments.culqi_unreachable')], 503);
        }
        if (($verified['object'] ?? null) !== 'charge' || ($verified['state'] ?? null) !== 'Exitosa') {
            return response()->json(['received' => true]);
        }
        if (in_array(($verified['metadata']['payment_purpose'] ?? null), ['subscription', 'subscription_upgrade'], true)) {
            return $this->confirmSubscription($verified, $chargeId, $eventId, $culqi, $subscriptionPayments);
        }

        return $this->confirmTicket($verified, $chargeId, $eventId, $culqi, $payments);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleOrder(array $payload, string $eventId, CulqiGateway $culqi, BookingPaymentService $payments, SubscriptionPaymentService $subscriptionPayments): JsonResponse
    {
        $incoming = $this->resource($payload);
        $orderId = (string) ($incoming['id'] ?? '');
        if ($orderId === '') {
            return response()->json(['received' => true]);
        }
        try {
            $order = $culqi->retrieveOrder($orderId);
        } catch (CulqiException) {
            return response()->json(['message' => __('payments.culqi_unreachable')], 503);
        }
        if (($order['state'] ?? null) !== 'paid') {
            return response()->json(['received' => true]);
        }
        if (in_array(($order['metadata']['payment_purpose'] ?? null), ['subscription', 'subscription_upgrade'], true)) {
            return $this->confirmSubscription($order, $orderId, $eventId, $culqi, $subscriptionPayments, 'pagoefectivo');
        }
        $code = $order['metadata']['reservation_code'] ?? $order['order_number'] ?? null;
        $reservation = Reservation::query()->where('code', $code)->first();
        if ($reservation === null) {
            Log::warning('Webhook de Culqi sin reserva.', ['orden' => $orderId]);

            return response()->json(['received' => true]);
        }
        $charged = (int) ($order['amount'] ?? 0);
        if ($charged !== $culqi->ticketCents($reservation) || ! $this->payable($reservation)) {
            $this->holdForReview($reservation, $orderId, $charged);

            return response()->json(['received' => true]);
        }
        $wasConfirmed = $reservation->status === 'confirmed';
        try {
            $confirmed = $payments->confirm($reservation, [
                'method' => 'pagoefectivo',
                'provider' => 'culqi',
                'provider_reference' => $orderId,
                'webhook_event_id' => $eventId !== '' ? $eventId : null,
                'provider_payload' => $culqi->safePayload($order),
            ]);
        } catch (HttpExceptionInterface) {
            $this->holdForReview($reservation, $orderId, $charged);

            return response()->json(['received' => true]);
        }
        $payment = Payment::query()->where('provider_reference', $orderId)->first();
        if (! $wasConfirmed && $payment !== null && $confirmed->status === 'confirmed') {
            $culqi->recordConfirmation($confirmed, $payment);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $verified
     */
    private function confirmTicket(array $verified, string $chargeId, string $eventId, CulqiGateway $culqi, BookingPaymentService $payments): JsonResponse
    {
        $existing = Payment::query()->where('provider_reference', $chargeId)->first();
        if ($existing && in_array($existing->status, ['confirmed', 'succeeded', 'refunded', 'review_required'], true)) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }
        $code = $verified['metadata']['reservation_code'] ?? null;
        $reservation = Reservation::query()->where('code', $code)->first();
        if ($reservation === null) {
            Log::warning('Webhook de Culqi sin reserva.', ['cargo' => $chargeId]);

            return response()->json(['received' => true]);
        }
        $charged = (int) ($verified['amount'] ?? 0);
        $expected = $culqi->ticketCents($reservation);
        if ($charged !== $expected) {
            $culqi->releaseCharge($reservation, $chargeId, $charged, 'solicitud_comprador');

            return response()->json(['received' => true]);
        }
        if ($reservation->status === 'confirmed') {
            $culqi->releaseCharge($reservation, $chargeId, $charged, 'duplicado');

            return response()->json(['received' => true, 'duplicate' => true]);
        }
        if (! $this->payable($reservation)) {
            $culqi->releaseCharge($reservation, $chargeId, $charged, 'solicitud_comprador');

            return response()->json(['received' => true]);
        }
        $wasConfirmed = false;
        try {
            $confirmed = $payments->confirm($reservation, [
                'method' => str_starts_with((string) ($verified['source']['id'] ?? ''), 'ype_') ? 'yape' : 'card',
                'provider' => 'culqi',
                'provider_reference' => $chargeId,
                'webhook_event_id' => $eventId !== '' ? $eventId : null,
                'provider_payload' => $culqi->safePayload($verified),
            ]);
        } catch (HttpExceptionInterface) {
            $culqi->releaseCharge($reservation->fresh() ?? $reservation, $chargeId, $charged, 'solicitud_comprador');

            return response()->json(['received' => true]);
        }
        $payment = Payment::query()->where('provider_reference', $chargeId)->first();
        if (! $wasConfirmed && $payment !== null && $confirmed->status === 'confirmed') {
            $culqi->recordConfirmation($confirmed, $payment);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $providerPayload
     */
    private function confirmSubscription(
        array $providerPayload,
        string $providerReference,
        string $eventId,
        CulqiGateway $culqi,
        SubscriptionPaymentService $payments,
        ?string $method = null,
    ): JsonResponse {
        $subscription = Subscription::query()->find($providerPayload['metadata']['subscription_id'] ?? null);
        if ($subscription === null) {
            return response()->json(['received' => true]);
        }
        $existing = Payment::query()->where('provider_reference', $providerReference)->first();
        if ($existing && in_array($existing->status, ['confirmed', 'refunded', 'voided', 'review_required'], true)) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }
        $upgradePlanId = $providerPayload['metadata']['upgrade_plan_id'] ?? null;
        $upgradePlan = $upgradePlanId ? SubscriptionPlan::query()->where('is_active', true)->find($upgradePlanId) : null;
        $months = max(1, min(24, (int) ($providerPayload['metadata']['months'] ?? 1)));
        $expectedAmount = $upgradePlan
            ? $culqi->cents((float) $payments->upgradeQuote($subscription, $upgradePlan)['amount'])
            : $culqi->cents((float) $subscription->monthly_price * $months);
        $safe = $culqi->safePayload($providerPayload);
        if ((int) ($providerPayload['amount'] ?? 0) !== $expectedAmount) {
            $refunded = str_starts_with($providerReference, 'chr_')
                && $culqi->refund($providerReference, (int) ($providerPayload['amount'] ?? 0), 'solicitud_comprador');
            Payment::query()->updateOrCreate(['provider_reference' => $providerReference], [
                'reservation_id' => null,
                'subscription_id' => $subscription->id,
                'purpose' => $upgradePlan ? 'subscription_upgrade' : 'subscription',
                'method' => $method ?? 'unknown',
                'provider' => 'culqi',
                'webhook_event_id' => $eventId !== '' ? $eventId : null,
                'amount' => number_format(((int) ($providerPayload['amount'] ?? 0)) / 100, 2, '.', ''),
                'currency_code' => $subscription->currency,
                'status' => $refunded ? 'refunded' : 'review_required',
                'requires_review' => ! $refunded,
                'review_reason' => $refunded ? null : 'El monto confirmado por Culqi no coincide con el precio del plan.',
                'provider_payload' => $safe,
            ]);

            return response()->json(['received' => true, 'requires_review' => ! $refunded]);
        }
        $paymentData = [
            'method' => $method ?? (str_starts_with((string) ($providerPayload['source']['id'] ?? ''), 'ype_') ? 'yape' : 'card'),
            'provider' => 'culqi',
            'provider_reference' => $providerReference,
            'webhook_event_id' => $eventId !== '' ? $eventId : null,
            'provider_payload' => $safe,
        ];
        $upgradePlan
            ? $payments->confirmUpgrade($subscription, $upgradePlan, $paymentData)
            : $payments->confirm($subscription, $paymentData, $months);

        return response()->json(['received' => true]);
    }

    private function holdForReview(Reservation $reservation, string $orderId, int $cents): void
    {
        $existing = Payment::query()->where('provider_reference', $orderId)->first();
        if ($existing && in_array($existing->status, ['confirmed', 'succeeded', 'refunded'], true)) {
            return;
        }
        Payment::query()->updateOrCreate(['provider_reference' => $orderId], [
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => 'pagoefectivo',
            'provider' => 'culqi',
            'amount' => number_format($cents / 100, 2, '.', ''),
            'currency_code' => 'PEN',
            'status' => 'review_required',
            'requires_review' => true,
            'review_reason' => __('payments.not_payable'),
            'provider_payload' => ['id' => $orderId],
        ]);
        Log::warning('Orden de Culqi sin confirmar la reserva.', ['orden' => $orderId, 'reserva' => $reservation->code]);
    }

    /**
     * Culqi envía el recurso en data, a veces anidado en data.object y a veces como JSON.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resource(array $payload): array
    {
        $data = $payload['data'] ?? [];
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['object']) && is_array($data['object'])) {
            return $data['object'];
        }

        return $data;
    }

    private function payable(Reservation $reservation): bool
    {
        return $reservation->status === 'pending_payment' && ! $reservation->expires_at?->isPast();
    }
}
