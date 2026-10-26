<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_payment_activates_subscription_immediately_and_is_idempotent(): void
    {
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Lodge Pago SAC', 'commercial_name' => 'Lodge Pago',
            'ruc' => '20222222222', 'email' => 'pago@test.pe', 'contact_name' => 'Rosa', 'status' => 'active',
        ]);
        $plan = SubscriptionPlan::create([
            'code' => 'payment-test', 'name' => 'Básico', 'monthly_price' => 300, 'currency' => 'PEN', 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => 'pending',
            'monthly_price' => 300, 'currency' => 'PEN', 'starts_on' => today(),
        ]);
        $paymentData = [
            'method' => 'card', 'provider' => 'culqi', 'provider_reference' => 'chr_subscription_1',
            'provider_payload' => ['state' => 'Exitosa'],
        ];

        $service = app(SubscriptionPaymentService::class);
        $service->confirm($subscription, $paymentData);
        $firstEndDate = $subscription->fresh()->ends_on;
        $service->confirm($subscription, $paymentData);

        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->ends_on->equalTo($firstEndDate));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'purpose' => 'subscription',
            'status' => 'confirmed',
            'requires_review' => false,
        ]);
    }

    public function test_upgrade_charges_only_the_prorated_difference_and_keeps_the_billing_date(): void
    {
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Lodge Upgrade SAC',
            'commercial_name' => 'Lodge Upgrade', 'ruc' => '20333333333', 'status' => 'active',
        ]);
        $basic = SubscriptionPlan::create(['code' => 'basic-upgrade', 'name' => 'Básico', 'monthly_price' => 300, 'currency' => 'PEN', 'is_active' => true]);
        $professional = SubscriptionPlan::create(['code' => 'professional-upgrade', 'name' => 'Profesional', 'monthly_price' => 600, 'currency' => 'PEN', 'benefits' => ['features' => ['Más campañas']], 'is_active' => true]);
        $subscription = Subscription::create([
            'organization_id' => $organization->id, 'subscription_plan_id' => $basic->id,
            'status' => 'active', 'monthly_price' => 300, 'currency' => 'PEN',
            'starts_on' => today()->subDays(15), 'ends_on' => today()->addDays(15),
        ]);
        $originalEndDate = $subscription->ends_on->copy();
        $service = app(SubscriptionPaymentService::class);
        $quote = $service->upgradeQuote($subscription, $professional);

        $service->confirmUpgrade($subscription, $professional, [
            'method' => 'card', 'provider' => 'culqi', 'provider_reference' => 'upgrade-payment-1',
        ]);

        $updated = $subscription->fresh();
        $this->assertSame($professional->id, $updated->subscription_plan_id);
        $this->assertSame('600.00', $updated->monthly_price);
        $this->assertTrue($updated->ends_on->equalTo($originalEndDate));
        $this->assertGreaterThan(0, $quote['amount']);
        $this->assertLessThan(300, $quote['amount']);
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'purpose' => 'subscription_upgrade',
            'amount' => $quote['amount'],
            'status' => 'confirmed',
        ]);
    }

    public function test_downgrade_is_scheduled_for_renewal_and_can_be_cancelled(): void
    {
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Lodge Downgrade SAC',
            'commercial_name' => 'Lodge Downgrade', 'ruc' => '20444444444', 'status' => 'active',
        ]);
        $basic = SubscriptionPlan::create(['code' => 'basic-downgrade', 'name' => 'Básico', 'monthly_price' => 300, 'currency' => 'PEN', 'is_active' => true]);
        $professional = SubscriptionPlan::create(['code' => 'professional-downgrade', 'name' => 'Profesional', 'monthly_price' => 750, 'currency' => 'PEN', 'is_active' => true]);
        $subscription = Subscription::create([
            'organization_id' => $organization->id, 'subscription_plan_id' => $professional->id,
            'status' => 'active', 'monthly_price' => 750, 'currency' => 'PEN',
            'starts_on' => today(), 'ends_on' => today()->addMonth(),
        ]);

        $service = app(SubscriptionPaymentService::class);
        $service->scheduleDowngrade($subscription, $basic);
        $scheduled = $subscription->fresh();

        $this->assertSame($professional->id, $scheduled->subscription_plan_id);
        $this->assertSame($basic->id, $scheduled->scheduled_plan_id);
        $this->assertTrue($scheduled->scheduled_plan_starts_on->equalTo($scheduled->ends_on));
        $this->assertSame('750.00', $scheduled->monthly_price);

        $service->cancelScheduledChange($scheduled);
        $this->assertNull($subscription->fresh()->scheduled_plan_id);
        $this->assertNull($subscription->fresh()->scheduled_plan_starts_on);
    }

    public function test_scheduled_downgrade_is_applied_when_the_next_cycle_is_paid(): void
    {
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Lodge Renewal SAC',
            'commercial_name' => 'Lodge Renewal', 'ruc' => '20555555555', 'status' => 'active',
        ]);
        $basic = SubscriptionPlan::create(['code' => 'basic-renewal', 'name' => 'Básico', 'monthly_price' => 300, 'currency' => 'PEN', 'benefits' => ['features' => ['Básico']], 'is_active' => true]);
        $professional = SubscriptionPlan::create(['code' => 'professional-renewal', 'name' => 'Profesional', 'monthly_price' => 750, 'currency' => 'PEN', 'is_active' => true]);
        $subscription = Subscription::create([
            'organization_id' => $organization->id, 'subscription_plan_id' => $professional->id,
            'scheduled_plan_id' => $basic->id, 'scheduled_plan_starts_on' => today(),
            'status' => 'active', 'monthly_price' => 750, 'currency' => 'PEN',
            'starts_on' => today()->subMonth(), 'ends_on' => today(),
        ]);

        $service = app(SubscriptionPaymentService::class);
        $this->assertSame(300.0, $service->renewalAmount($subscription));
        $service->confirm($subscription, [
            'method' => 'card', 'provider' => 'culqi', 'provider_reference' => 'downgrade-renewal-1',
        ]);

        $updated = $subscription->fresh();
        $this->assertSame($basic->id, $updated->subscription_plan_id);
        $this->assertSame('300.00', $updated->monthly_price);
        $this->assertNull($updated->scheduled_plan_id);
        $this->assertDatabaseHas('payments', ['provider_reference' => 'downgrade-renewal-1', 'amount' => 300]);
    }
}
