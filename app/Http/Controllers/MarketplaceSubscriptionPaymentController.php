<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPaymentRequest;
use App\Models\SubscriptionPlan;
use App\Services\CulqiException;
use App\Services\CulqiGateway;
use App\Services\SubscriptionPaymentService;
use App\Services\SubscriptionTransfers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketplaceSubscriptionPaymentController extends Controller
{
    public function show(Request $request): View
    {
        $subscription = $this->subscriptionFor($request);
        $payments = app(SubscriptionPaymentService::class);
        $culqi = app(CulqiGateway::class);
        $upgradePlan = $request->filled('plan') ? SubscriptionPlan::where('is_active', true)->findOrFail($request->integer('plan')) : null;
        $upgradeQuote = $upgradePlan ? $payments->upgradeQuote($subscription, $upgradePlan) : null;

        return view('marketplace.subscription-payment', [
            'subscription' => $subscription,
            'organization' => $subscription->organization,
            'sandboxEnabled' => config('payments.sandbox_enabled'),
            'culqiEnabled' => $culqi->ready(),
            'culqiTestMode' => $culqi->testMode(),
            'upgradePlan' => $upgradePlan,
            'upgradeQuote' => $upgradeQuote,
            'renewalPlan' => $payments->renewalPlan($subscription),
            'renewalAmount' => $payments->renewalAmount($subscription),
        ]);
    }

    public function sandboxConfirm(Request $request, SubscriptionPaymentService $payments): RedirectResponse
    {
        abort_unless(config('payments.sandbox_enabled'), 404);
        $data = $request->validate(['method' => ['required', 'in:yape,plin,card'], 'plan_id' => ['nullable', 'exists:subscription_plans,id']]);
        $subscription = $this->subscriptionFor($request);
        $paymentData = [
            'method' => $data['method'],
            'provider' => 'sandbox',
            'provider_reference' => 'SUB-DEMO-'.Str::upper(Str::random(12)),
            'provider_payload' => ['mode' => 'sandbox'],
        ];
        if (! empty($data['plan_id'])) {
            $payments->confirmUpgrade($subscription, SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id']), $paymentData);
        } else {
            $payments->confirm($subscription, $paymentData);
        }

        return redirect()->route('marketplace.dashboard', ['tab' => 'plan'])
            ->with('success', ! empty($data['plan_id']) ? 'Cambio confirmado. Tu plan superior ya está activo.' : 'Pago confirmado. Tu plan ya está activo.');
    }

    public function scheduleDowngrade(Request $request, SubscriptionPaymentService $payments): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id']]);
        $subscription = $this->subscriptionFor($request);
        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($data['plan_id']);
        $payments->scheduleDowngrade($subscription, $plan);

        return redirect()->route('marketplace.dashboard', ['tab' => 'plan'])
            ->with('success', "El cambio a {$plan->name} quedó programado para tu próxima renovación.");
    }

    public function cancelScheduledChange(Request $request, SubscriptionPaymentService $payments): RedirectResponse
    {
        $payments->cancelScheduledChange($this->subscriptionFor($request));

        return redirect()->route('marketplace.dashboard', ['tab' => 'plan'])
            ->with('success', 'Se canceló el cambio de plan programado.');
    }

    public function charge(Request $request, SubscriptionPaymentService $payments, CulqiGateway $culqi): JsonResponse
    {
        abort_unless($culqi->ready(), 503, __('payments.culqi_off'));
        $data = $request->validate([
            'source_id' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_]+$/'],
            'plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'amount' => ['sometimes', 'integer'],
        ]);
        $subscription = $this->subscriptionFor($request);
        $upgradePlan = ! empty($data['plan_id']) ? SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id']) : null;
        $amount = $upgradePlan ? $payments->upgradeQuote($subscription, $upgradePlan)['amount'] : $payments->renewalAmount($subscription);
        abort_if(! $upgradePlan && $subscription->status === 'active' && $subscription->ends_on?->isFuture(), 422, 'Tu plan ya está activo.');
        $cents = $this->amountInCents($amount);
        if (array_key_exists('amount', $data) && (int) $data['amount'] !== $cents) {
            return response()->json(['message' => __('payments.amount_rejected')], 422);
        }

        $organization = $subscription->organization;
        try {
            $charge = $culqi->createCharge([
                'amount' => $cents,
                'currency_code' => $upgradePlan?->currency ?? $payments->renewalPlan($subscription)->currency,
                'email' => $organization->email,
                'source_id' => $data['source_id'],
                'capture' => true,
                'description' => 'Suscripción NavegaYA '.$organization->commercial_name,
                'metadata' => ['payment_purpose' => $upgradePlan ? 'subscription_upgrade' : 'subscription', 'subscription_id' => (string) $subscription->id, 'upgrade_plan_id' => (string) ($upgradePlan?->id ?? ''), 'months' => '1'],
            ]);
        } catch (CulqiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if (($charge['object'] ?? null) !== 'charge' || ($charge['state'] ?? null) !== 'Exitosa' || (int) ($charge['amount'] ?? 0) !== $cents) {
            if (is_string($charge['id'] ?? null) && str_starts_with($charge['id'], 'chr_') && (int) ($charge['amount'] ?? 0) !== $cents) {
                $culqi->refund($charge['id'], (int) $charge['amount'], 'solicitud_comprador');
            }

            return response()->json(['message' => __('payments.culqi_rejected')], 422);
        }

        $paymentData = [
            'method' => str_starts_with($data['source_id'], 'ype_') ? 'yape' : 'card',
            'provider' => 'culqi',
            'provider_reference' => $charge['id'],
            'provider_payload' => $culqi->safePayload($charge),
        ];
        $upgradePlan ? $payments->confirmUpgrade($subscription, $upgradePlan, $paymentData) : $payments->confirm($subscription, $paymentData);

        return response()->json(['redirect' => route('marketplace.dashboard', ['tab' => 'plan'])]);
    }

    public function order(Request $request, SubscriptionPaymentService $payments, CulqiGateway $culqi): JsonResponse
    {
        abort_unless($culqi->ready(), 503, __('payments.culqi_off'));
        $subscription = $this->subscriptionFor($request);
        $data = $request->validate(['plan_id' => ['nullable', 'exists:subscription_plans,id']]);
        $upgradePlan = ! empty($data['plan_id']) ? SubscriptionPlan::where('is_active', true)->findOrFail($data['plan_id']) : null;
        $amount = $upgradePlan ? $payments->upgradeQuote($subscription, $upgradePlan)['amount'] : $payments->renewalAmount($subscription);
        abort_if(! $upgradePlan && $subscription->status === 'active' && $subscription->ends_on?->isFuture(), 422, 'Tu plan ya está activo.');
        $organization = $subscription->organization;
        $reference = 'SUB-'.$subscription->id.'-'.now()->format('YmdHis');
        $names = preg_split('/\s+/', trim((string) $organization->contact_name), 2);

        $cents = $this->amountInCents($amount);
        try {
            $order = $culqi->createOrder([
                'amount' => $cents,
                'currency_code' => $upgradePlan?->currency ?? $payments->renewalPlan($subscription)->currency,
                'description' => 'Suscripción NavegaYA '.$organization->commercial_name,
                'order_number' => $reference,
                'client_details' => [
                    'first_name' => $names[0] ?? 'Cliente',
                    'last_name' => $names[1] ?? '-',
                    'email' => $organization->email,
                    'phone_number' => preg_replace('/\D+/', '', (string) $organization->phone),
                ],
                'expiration_date' => now()->addDay()->timestamp,
                'confirm' => true,
                'metadata' => ['payment_purpose' => $upgradePlan ? 'subscription_upgrade' : 'subscription', 'subscription_id' => (string) $subscription->id, 'upgrade_plan_id' => (string) ($upgradePlan?->id ?? ''), 'months' => '1'],
            ]);
        } catch (CulqiException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if (($order['object'] ?? null) !== 'order' || blank($order['id'] ?? null) || (int) ($order['amount'] ?? 0) !== $cents) {
            return response()->json(['message' => __('payments.order_failed')], 422);
        }
        Payment::updateOrCreate(['provider_reference' => $order['id']], [
            'reservation_id' => null,
            'subscription_id' => $subscription->id,
            'purpose' => $upgradePlan ? 'subscription_upgrade' : 'subscription',
            'method' => 'pagoefectivo',
            'provider' => 'culqi',
            'amount' => $amount,
            'currency_code' => $upgradePlan?->currency ?? $payments->renewalPlan($subscription)->currency,
            'status' => 'pending',
            'provider_payload' => $culqi->safePayload($order),
        ]);

        return response()->json(['order_id' => $order['id'], 'payment_code' => $order['payment_code'] ?? null]);
    }

    public function requestTransfer(Request $request, SubscriptionTransfers $transfers): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(SubscriptionPaymentRequest::METHODS))],
            'months' => ['required', 'integer', Rule::in([1, 3, 6, 12])],
            'operation_number' => ['required', 'string', 'max:80'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'voucher' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'voucher.required' => 'Adjunta la foto o el PDF del comprobante.',
            'voucher.mimes' => 'El comprobante debe ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'voucher.max' => 'El comprobante no puede pesar más de 5 MB.',
            'operation_number.required' => 'Escribe el número de operación.',
        ]);
        $transfers->request($request, $this->subscriptionFor($request), $data, $request->file('voucher'));

        return redirect()->route('marketplace.dashboard', ['tab' => 'plan'])
            ->with('success', 'Recibimos tu comprobante. NavegaYA lo revisará y activará tu plan.');
    }

    private function subscriptionFor(Request $request): Subscription
    {
        $organization = $request->user()->organizations()
            ->where('organizations.type', 'marketplace_business')->firstOrFail();

        return $organization->subscriptions()->with(['organization', 'plan', 'scheduledPlan'])->latest()->firstOrFail();
    }

    private function amountInCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
