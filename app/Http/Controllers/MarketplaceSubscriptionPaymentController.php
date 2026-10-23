<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\SubscriptionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MarketplaceSubscriptionPaymentController extends Controller
{
    public function show(Request $request): View
    {
        $subscription = $this->subscriptionFor($request);

        return view('marketplace.subscription-payment', [
            'subscription' => $subscription,
            'organization' => $subscription->organization,
            'sandboxEnabled' => config('payments.sandbox_enabled'),
            'culqiEnabled' => config('services.culqi.enabled'),
        ]);
    }

    public function sandboxConfirm(Request $request, SubscriptionPaymentService $payments): RedirectResponse
    {
        abort_unless(config('payments.sandbox_enabled'), 404);
        $data = $request->validate(['method' => ['required', 'in:yape,plin,card']]);
        $subscription = $this->subscriptionFor($request);

        $payments->confirm($subscription, [
            'method' => $data['method'],
            'provider' => 'sandbox',
            'provider_reference' => 'SUB-DEMO-'.Str::upper(Str::random(12)),
            'provider_payload' => ['mode' => 'sandbox'],
        ]);

        return redirect()->route('marketplace.dashboard', ['tab' => 'plan'])
            ->with('success', 'Pago confirmado. Tu plan ya está activo.');
    }

    public function charge(Request $request, SubscriptionPaymentService $payments): JsonResponse
    {
        abort_unless(config('services.culqi.enabled') && config('services.culqi.secret_key'), 503);
        $data = $request->validate(['source_id' => ['required', 'string', 'max:120']]);
        $subscription = $this->subscriptionFor($request);
        abort_if($subscription->status === 'active' && $subscription->ends_on?->isFuture(), 422, 'Tu plan ya está activo.');

        $organization = $subscription->organization;
        $response = Http::withToken(config('services.culqi.secret_key'))->acceptJson()->asJson()->timeout(20)
            ->post(rtrim(config('services.culqi.api_url'), '/').'/charges', [
                'amount' => $this->amountInCents($subscription),
                'currency_code' => $subscription->currency,
                'email' => $organization->email,
                'source_id' => $data['source_id'],
                'capture' => true,
                'description' => "Suscripción NavegaYA {$organization->commercial_name}",
                'metadata' => ['payment_purpose' => 'subscription', 'subscription_id' => (string) $subscription->id, 'months' => '1'],
            ]);

        if ($response->failed()) {
            return response()->json(['message' => $response->json('user_message') ?? $response->json('merchant_message') ?? 'Culqi no pudo procesar el pago.'], 422);
        }

        $charge = $response->json();
        abort_unless(($charge['object'] ?? null) === 'charge' && ($charge['state'] ?? null) === 'Exitosa', 422);
        abort_unless((int) ($charge['amount'] ?? 0) === $this->amountInCents($subscription), 422);

        $payments->confirm($subscription, [
            'method' => str_starts_with($data['source_id'], 'ype_') ? 'yape' : 'card',
            'provider' => 'culqi',
            'provider_reference' => $charge['id'],
            'provider_payload' => $charge,
        ]);

        return response()->json(['redirect' => route('marketplace.dashboard', ['tab' => 'plan'])]);
    }

    public function order(Request $request): JsonResponse
    {
        abort_unless(config('services.culqi.enabled') && config('services.culqi.secret_key'), 503);
        $subscription = $this->subscriptionFor($request);
        abort_if($subscription->status === 'active' && $subscription->ends_on?->isFuture(), 422, 'Tu plan ya está activo.');
        $organization = $subscription->organization;
        $reference = 'SUB-'.$subscription->id.'-'.now()->format('YmdHis');
        $names = preg_split('/\s+/', trim((string) $organization->contact_name), 2);

        $response = Http::withToken(config('services.culqi.secret_key'))->acceptJson()->asJson()->timeout(20)
            ->post(rtrim(config('services.culqi.api_url'), '/').'/orders', [
                'amount' => $this->amountInCents($subscription),
                'currency_code' => $subscription->currency,
                'description' => "Suscripción NavegaYA {$organization->commercial_name}",
                'order_number' => $reference,
                'client_details' => [
                    'first_name' => $names[0] ?? 'Cliente',
                    'last_name' => $names[1] ?? '-',
                    'email' => $organization->email,
                    'phone_number' => preg_replace('/\D+/', '', (string) $organization->phone),
                ],
                'expiration_date' => now()->addDay()->timestamp,
                'confirm' => true,
                'metadata' => ['payment_purpose' => 'subscription', 'subscription_id' => (string) $subscription->id, 'months' => '1'],
            ]);

        if ($response->failed()) {
            return response()->json(['message' => $response->json('user_message') ?? $response->json('merchant_message') ?? 'No se pudo generar el código PagoEfectivo.'], 422);
        }

        $order = $response->json();
        abort_unless(($order['object'] ?? null) === 'order' && filled($order['id'] ?? null), 422);
        Payment::updateOrCreate(['provider_reference' => $order['id']], [
            'reservation_id' => null,
            'subscription_id' => $subscription->id,
            'purpose' => 'subscription',
            'method' => 'pagoefectivo',
            'provider' => 'culqi',
            'amount' => $subscription->monthly_price,
            'currency_code' => $subscription->currency,
            'status' => 'pending',
            'provider_payload' => $order,
        ]);

        return response()->json(['order_id' => $order['id'], 'payment_code' => $order['payment_code'] ?? null]);
    }

    private function subscriptionFor(Request $request): Subscription
    {
        $organization = $request->user()->organizations()
            ->where('organizations.type', 'marketplace_business')->firstOrFail();

        return $organization->subscriptions()->with(['organization', 'plan'])->latest()->firstOrFail();
    }

    private function amountInCents(Subscription $subscription): int
    {
        return (int) round((float) $subscription->monthly_price * 100);
    }
}
