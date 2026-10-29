<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\BookingPaymentService;
use App\Services\SubscriptionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentWebhookController extends Controller
{
    public function culqi(Request $request, BookingPaymentService $payments, SubscriptionPaymentService $subscriptionPayments): JsonResponse
    {
        $raw = $request->getContent();
        $secret = (string) config('services.culqi.webhook_secret');
        $signature = (string) ($request->header('X-Culqi-Signature') ?? $request->header('Culqi-Signature'));
        abort_unless($secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $raw, $secret), $signature), 401);

        $payload = $request->json()->all();
        $event = (string) ($payload['type'] ?? $payload['event'] ?? '');
        if ($event === 'order.status.changed') {
            return $this->handleOrder($payload, $raw, $payments, $subscriptionPayments);
        }
        if (! in_array($event, ['charge.succeeded', 'charge.creation.succeeded'], true)) {
            return response()->json(['received' => true]);
        }
        $eventId = (string) ($payload['id'] ?? 'evt-'.hash('sha256', $raw));
        if (Payment::where('webhook_event_id', $eventId)->exists()) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }
        $charge = $payload['data']['object'] ?? $payload['data'] ?? [];
        $chargeId = (string) ($charge['id'] ?? '');
        abort_unless($chargeId !== '', 422);

        $verified = Http::withToken(config('services.culqi.secret_key'))->acceptJson()
            ->get(rtrim(config('services.culqi.api_url'), '/').'/charges/'.$chargeId)
            ->throw()->json();
        abort_unless(($verified['object'] ?? null) === 'charge' && ($verified['state'] ?? null) === 'Exitosa', 422);
        if (in_array(($verified['metadata']['payment_purpose'] ?? null), ['subscription', 'subscription_upgrade'], true)) {
            return $this->confirmSubscription($verified, $chargeId, $eventId, $subscriptionPayments);
        }
        $code = $verified['metadata']['reservation_code'] ?? $charge['metadata']['reservation_code'] ?? null;
        $reservation = Reservation::where('code', $code)->firstOrFail();
        abort_unless((int) ($verified['amount'] ?? 0) === (int) round((float) $reservation->total_amount * 100), 422);

        $payments->confirm($reservation, [
            'method' => str_starts_with((string) ($verified['source']['id'] ?? ''), 'ype_') ? 'yape' : 'card',
            'provider' => 'culqi',
            'provider_reference' => $chargeId,
            'webhook_event_id' => $eventId,
            'provider_payload' => $verified,
        ]);

        Payment::where('provider_reference', $chargeId)->update(['webhook_event_id' => $eventId]);

        return response()->json(['received' => true]);
    }

    private function handleOrder(array $payload, string $raw, BookingPaymentService $payments, SubscriptionPaymentService $subscriptionPayments): JsonResponse
    {
        $eventId = (string) ($payload['id'] ?? 'evt-'.hash('sha256', $raw));
        if (Payment::where('webhook_event_id', $eventId)->exists()) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }
        $incoming = $payload['data']['object'] ?? $payload['data'] ?? [];
        $orderId = (string) ($incoming['id'] ?? '');
        abort_unless($orderId !== '', 422);
        $order = Http::withToken(config('services.culqi.secret_key'))->acceptJson()
            ->get(rtrim(config('services.culqi.api_url'), '/').'/orders/'.$orderId)->throw()->json();
        if (($order['state'] ?? null) !== 'paid') {
            return response()->json(['received' => true, 'state' => $order['state'] ?? 'unknown']);
        }
        if (in_array(($order['metadata']['payment_purpose'] ?? null), ['subscription', 'subscription_upgrade'], true)) {
            return $this->confirmSubscription($order, $orderId, $eventId, $subscriptionPayments, 'pagoefectivo');
        }
        $code = $order['metadata']['reservation_code'] ?? $order['order_number'] ?? null;
        $reservation = Reservation::where('code', $code)->firstOrFail();
        abort_unless((int) ($order['amount'] ?? 0) === (int) round((float) $reservation->total_amount * 100), 422);
        $payments->confirm($reservation, [
            'method' => 'pagoefectivo', 'provider' => 'culqi', 'provider_reference' => $orderId,
            'webhook_event_id' => $eventId, 'provider_payload' => $order,
        ]);
        Payment::where('provider_reference', $orderId)->update([
            'status' => 'confirmed', 'paid_at' => now(), 'webhook_event_id' => $eventId, 'provider_payload' => $order,
        ]);

        return response()->json(['received' => true]);
    }

    private function confirmSubscription(
        array $providerPayload,
        string $providerReference,
        string $eventId,
        SubscriptionPaymentService $payments,
        ?string $method = null,
    ): JsonResponse {
        $subscription = Subscription::query()->findOrFail($providerPayload['metadata']['subscription_id'] ?? null);
        $upgradePlanId = $providerPayload['metadata']['upgrade_plan_id'] ?? null;
        $upgradePlan = $upgradePlanId ? SubscriptionPlan::where('is_active', true)->findOrFail($upgradePlanId) : null;
        $months = max(1, min(24, (int) ($providerPayload['metadata']['months'] ?? 1)));
        $expectedAmount = $upgradePlan
            ? (int) round($payments->upgradeQuote($subscription, $upgradePlan)['amount'] * 100)
            : (int) round((float) $subscription->monthly_price * $months * 100);
        if ((int) ($providerPayload['amount'] ?? 0) !== $expectedAmount) {
            Payment::updateOrCreate(['provider_reference' => $providerReference], [
                'reservation_id' => null,
                'subscription_id' => $subscription->id,
                'purpose' => $upgradePlan ? 'subscription_upgrade' : 'subscription',
                'method' => $method ?? 'unknown',
                'provider' => 'culqi',
                'webhook_event_id' => $eventId,
                'amount' => (float) (($providerPayload['amount'] ?? 0) / 100),
                'currency_code' => $subscription->currency,
                'status' => 'review_required',
                'requires_review' => true,
                'review_reason' => 'El monto confirmado por Culqi no coincide con el precio del plan.',
                'provider_payload' => $providerPayload,
            ]);

            return response()->json(['received' => true, 'requires_review' => true], 202);
        }

        $paymentData = [
            'method' => $method ?? (str_starts_with((string) ($providerPayload['source']['id'] ?? ''), 'ype_') ? 'yape' : 'card'),
            'provider' => 'culqi',
            'provider_reference' => $providerReference,
            'webhook_event_id' => $eventId,
            'provider_payload' => $providerPayload,
        ];
        $upgradePlan
            ? $payments->confirmUpgrade($subscription, $upgradePlan, $paymentData)
            : $payments->confirm($subscription, $paymentData, $months);

        return response()->json(['received' => true, $upgradePlan ? 'subscription_upgraded' : 'subscription_activated' => true]);
    }
}
