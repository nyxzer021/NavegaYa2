<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionPaymentService
{
    public function confirm(Subscription $subscription, array $paymentData, int $months = 1): Subscription
    {
        return DB::transaction(function () use ($subscription, $paymentData, $months): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $existingPayment = Payment::query()->where('provider_reference', $paymentData['provider_reference'])->first();

            if ($existingPayment?->status === 'confirmed') {
                return $subscription->refresh();
            }

            Payment::updateOrCreate(
                ['provider_reference' => $paymentData['provider_reference']],
                [
                    'reservation_id' => null,
                    'subscription_id' => $subscription->id,
                    'purpose' => 'subscription',
                    'method' => $paymentData['method'],
                    'provider' => $paymentData['provider'],
                    'webhook_event_id' => $paymentData['webhook_event_id'] ?? null,
                    'amount' => (float) $subscription->monthly_price * $months,
                    'currency_code' => $subscription->currency,
                    'commission_rate' => 0,
                    'commission_amount' => 0,
                    'operator_net' => 0,
                    'status' => 'confirmed',
                    'requires_review' => false,
                    'review_reason' => null,
                    'paid_at' => now(),
                    'provider_payload' => $paymentData['provider_payload'] ?? null,
                ],
            );

            $baseDate = $subscription->status === 'active' && $subscription->ends_on?->isFuture()
                ? $subscription->ends_on->copy()
                : today();

            $subscription->update([
                'status' => 'active',
                'starts_on' => $subscription->status === 'active' ? $subscription->starts_on : today(),
                'ends_on' => $baseDate->addMonths($months),
                'benefits_snapshot' => $subscription->benefits_snapshot ?: $subscription->plan?->benefits,
                'cancelled_at' => null,
            ]);

            return $subscription->refresh();
        });
    }
}
