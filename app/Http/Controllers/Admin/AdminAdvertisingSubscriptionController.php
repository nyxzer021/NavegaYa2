<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAdvertisingSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Subscription::query()
            ->with(['organization', 'plan'])
            ->where(function ($subscriptions): void {
                $subscriptions->where('status', '!=', 'pending')
                    ->orWhereNotExists(function ($active): void {
                        $active->selectRaw('1')
                            ->from('subscriptions as current_subscription')
                            ->whereColumn('current_subscription.organization_id', 'subscriptions.organization_id')
                            ->whereIn('current_subscription.status', ['active', 'overdue']);
                    });
            })
            ->latest('starts_on');
        if ($request->filled('plan')) {
            $query->whereHas('plan', fn ($q) => $q->where('code', $request->string('plan')));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->whereHas('organization', fn ($q) => $q->where('commercial_name', 'like', "%{$search}%")->orWhere('legal_name', 'like', "%{$search}%")->orWhere('ruc', 'like', "%{$search}%"));
        }

        return view('admin.advertising-subscriptions.index', [
            'subscriptions' => $query->paginate(20)->withQueryString(),
            'plans' => SubscriptionPlan::orderBy('monthly_price')->get(),
            'organizations' => Organization::query()
                ->where(fn ($query) => $query->where('status', 'active')
                    ->orWhere(fn ($business) => $business->where('type', 'marketplace_business')->where('status', 'pending')))
                ->orderBy('commercial_name')->get(),
            'statusCounts' => Subscription::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
            'monthlyRevenue' => (float) Subscription::where('status', 'active')->sum('monthly_price'),
            'annualRevenue' => (float) Subscription::where('status', 'active')->sum('monthly_price') * 12,
            'paymentExceptions' => Payment::query()->whereIn('purpose', ['subscription', 'subscription_upgrade'])->where('requires_review', true)->with('subscription.organization', 'subscription.plan')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if (Subscription::where('organization_id', $data['organization_id'])->whereIn('status', ['active', 'overdue'])->exists()) {
            return back()->withErrors(['organization_id' => 'La empresa ya tiene una suscripción activa o pendiente de regularización.'])->withInput();
        }
        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        $startsOn = now()->parse($data['starts_on']);
        $subscription = Subscription::query()->where('organization_id', $data['organization_id'])->latest()->first();
        $values = ['subscription_plan_id' => $plan->id, 'monthly_price' => $plan->monthly_price, 'currency' => $plan->currency, 'benefits_snapshot' => $plan->benefits, 'starts_on' => $startsOn, 'ends_on' => $startsOn->copy()->addMonths((int) $data['months']), 'status' => 'active', 'cancelled_at' => null];
        if ($subscription) {
            $subscription->update($values);
        } else {
            $subscription = Subscription::create(['organization_id' => $data['organization_id']] + $values);
        }
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'manual_subscription_activation', 'subject_type' => Subscription::class, 'subject_id' => $subscription->id, 'changes' => ['reason' => $data['reason'], 'months' => (int) $data['months']], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'route_name' => $request->route()?->getName(), 'http_method' => $request->method(), 'created_at' => now()]);

        return back()->with('success', 'Excepción registrada y suscripción activada manualmente.');
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['subscription_plan_id' => ['required', 'exists:subscription_plans,id'], 'status' => ['required', Rule::in(['active', 'overdue', 'cancelled', 'expired'])]]);
        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        $subscription->update($data + ['monthly_price' => $plan->monthly_price, 'currency' => $plan->currency, 'benefits_snapshot' => $plan->benefits, 'cancelled_at' => $data['status'] === 'cancelled' ? now() : null]);

        return back()->with('success', 'Suscripción actualizada.');
    }

    public function renew(Request $request, Subscription $subscription): RedirectResponse
    {
        $months = $request->validate(['months' => ['required', 'integer', 'min:1', 'max:24']])['months'];
        $base = $subscription->ends_on && $subscription->ends_on->isFuture() ? $subscription->ends_on->copy() : today();
        $subscription->loadMissing('plan')->update(['ends_on' => $base->addMonths($months), 'status' => 'active', 'benefits_snapshot' => $subscription->plan?->benefits, 'cancelled_at' => null]);

        return back()->with('success', "Suscripción renovada por {$months} mes(es).");
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return back()->with('success', 'Suscripción cancelada y registrada como baja.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:500'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:99999'], 'ad_limit' => ['required', 'integer', 'min:0', 'max:99'],
            'campaign_days' => ['required', 'integer', 'min:1', 'max:365'], 'impressions' => ['nullable', 'integer', 'min:0'],
            'placements' => ['required', 'array', 'min:1'], 'placements.*' => ['in:home_carousel,home_sidebar,home_recommendations,home_popup'],
            'features' => ['nullable', 'string', 'max:2000'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $features = collect(preg_split('/\r\n|\r|\n/', (string) ($data['features'] ?? '')))->map(fn ($feature) => trim($feature))->filter()->values()->all();
        $plan->update([
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'monthly_price' => $data['monthly_price'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'benefits' => ['ad_limit' => (int) $data['ad_limit'], 'campaign_days' => (int) $data['campaign_days'], 'impressions' => (int) ($data['impressions'] ?? 0), 'allowed_placements' => $data['placements'], 'features' => $features],
        ]);

        return back()->with('success', 'Plan actualizado. Los cambios se aplicarán a nuevas compras y renovaciones.');
    }

    public function resolvePaymentException(Request $request, Payment $payment, SubscriptionPaymentService $payments): RedirectResponse
    {
        abort_unless($payment->purpose === 'subscription' && $payment->requires_review, 404);
        $data = $request->validate(['resolution' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
        if ($data['resolution'] === 'approve') {
            $payments->confirm($payment->subscription()->firstOrFail(), [
                'method' => $payment->method, 'provider' => $payment->provider, 'provider_reference' => $payment->provider_reference,
                'webhook_event_id' => $payment->webhook_event_id, 'provider_payload' => $payment->provider_payload,
            ]);
        } else {
            $payment->update(['status' => 'rejected', 'requires_review' => false, 'review_reason' => $data['reason']]);
        }
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'payment_exception_resolved', 'subject_type' => Payment::class, 'subject_id' => $payment->id, 'changes' => ['resolution' => $data['resolution'], 'reason' => $data['reason']], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'route_name' => $request->route()?->getName(), 'http_method' => $request->method(), 'created_at' => now()]);

        return back()->with('success', $data['resolution'] === 'approve' ? 'Pago aprobado y plan activado.' : 'Excepción rechazada y cerrada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['organization_id' => ['required', 'exists:organizations,id'], 'subscription_plan_id' => ['required', 'exists:subscription_plans,id'], 'starts_on' => ['required', 'date'], 'months' => ['required', 'integer', 'min:1', 'max:24'], 'reason' => ['required', 'string', 'min:10', 'max:500']]);
    }
}
