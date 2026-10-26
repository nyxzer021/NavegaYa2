<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class SubscriptionPaymentService
{
    public function renewalPlan(Subscription $subscription): SubscriptionPlan
    {
        if ($subscription->scheduled_plan_id && $subscription->scheduled_plan_starts_on?->lte(today())) {
            return SubscriptionPlan::query()->where('is_active', true)->findOrFail($subscription->scheduled_plan_id);
        }

        return $subscription->plan;
    }

    public function renewalAmount(Subscription $subscription): float
    {
        return (float) $this->renewalPlan($subscription)->monthly_price;
    }

    public function scheduleDowngrade(Subscription $subscription, SubscriptionPlan $newPlan): Subscription
    {
        abort_unless($subscription->status === 'active' && $subscription->ends_on?->isFuture(), 422, 'La suscripción debe estar activa para cambiar de plan.');
        abort_unless((float) $newPlan->monthly_price < (float) $subscription->monthly_price, 422, 'Selecciona un plan inferior al actual.');

        $subscription->update([
            'scheduled_plan_id' => $newPlan->id,
            'scheduled_plan_starts_on' => $subscription->ends_on,
        ]);

        return $subscription->refresh();
    }

    public function cancelScheduledChange(Subscription $subscription): Subscription
    {
        $subscription->update(['scheduled_plan_id' => null, 'scheduled_plan_starts_on' => null]);

        return $subscription->refresh();
    }

    public function upgradeQuote(Subscription $subscription, SubscriptionPlan $newPlan): array
    {
        abort_unless($subscription->status === 'active' && $subscription->ends_on?->isFuture(), 422, 'La suscripción debe estar activa para cambiar de plan.');
        abort_unless((float) $newPlan->monthly_price > (float) $subscription->monthly_price, 422, 'Selecciona un plan superior al actual.');

        $cycleEnd = $subscription->ends_on->copy();
        $cycleStart = $cycleEnd->copy()->subMonthNoOverflow();
        $cycleDays = max(1, $cycleStart->diffInDays($cycleEnd));
        $remainingDays = max(1, today()->diffInDays($cycleEnd));
        $dailyDifference = ((float) $newPlan->monthly_price - (float) $subscription->monthly_price) / $cycleDays;

        return [
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'cycle_days' => $cycleDays,
            'remaining_days' => $remainingDays,
            'current_price' => (float) $subscription->monthly_price,
            'new_price' => (float) $newPlan->monthly_price,
            'amount' => round($dailyDifference * $remainingDays, 2),
        ];
    }

    public function confirmUpgrade(Subscription $subscription, SubscriptionPlan $newPlan, array $paymentData): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan, $paymentData): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $existingPayment = Payment::query()->where('provider_reference', $paymentData['provider_reference'])->first();
            if ($existingPayment?->status === 'confirmed') {
                return $subscription->refresh();
            }

            $quote = $this->upgradeQuote($subscription, $newPlan);
            Payment::updateOrCreate(['provider_reference' => $paymentData['provider_reference']], [
                'reservation_id' => null,
                'subscription_id' => $subscription->id,
                'purpose' => 'subscription_upgrade',
                'method' => $paymentData['method'],
                'provider' => $paymentData['provider'],
                'webhook_event_id' => $paymentData['webhook_event_id'] ?? null,
                'amount' => $quote['amount'],
                'currency_code' => $newPlan->currency,
                'commission_rate' => 0,
                'commission_amount' => 0,
                'operator_net' => 0,
                'status' => 'confirmed',
                'requires_review' => false,
                'review_reason' => null,
                'paid_at' => now(),
                'provider_payload' => $paymentData['provider_payload'] ?? null,
            ]);

            $subscription->update([
                'subscription_plan_id' => $newPlan->id,
                'scheduled_plan_id' => null,
                'scheduled_plan_starts_on' => null,
                'monthly_price' => $newPlan->monthly_price,
                'currency' => $newPlan->currency,
                'benefits_snapshot' => $newPlan->benefits,
            ]);

            return $subscription->refresh();
        });
    }

    public function confirm(Subscription $subscription, array $paymentData, int $months = 1): Subscription
    {
        return DB::transaction(function () use ($subscription, $paymentData, $months): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $existingPayment = Payment::query()->where('provider_reference', $paymentData['provider_reference'])->first();

            if ($existingPayment?->status === 'confirmed') {
                return $subscription->refresh();
            }

            if ($subscription->scheduled_plan_id && $subscription->scheduled_plan_starts_on?->lte(today())) {
                $scheduledPlan = SubscriptionPlan::query()->where('is_active', true)->find($subscription->scheduled_plan_id);
                if ($scheduledPlan) {
                    $subscription->update([
                        'subscription_plan_id' => $scheduledPlan->id,
                        'monthly_price' => $scheduledPlan->monthly_price,
                        'currency' => $scheduledPlan->currency,
                        'benefits_snapshot' => $scheduledPlan->benefits,
                        'scheduled_plan_id' => null,
                        'scheduled_plan_starts_on' => null,
                    ]);
                    $subscription->refresh();
                }
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
