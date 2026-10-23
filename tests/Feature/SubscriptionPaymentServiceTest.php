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
}
