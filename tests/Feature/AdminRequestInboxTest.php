<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRequestInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_pending_requests_in_the_unified_inbox(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $business = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Lodge Sol SAC', 'commercial_name' => 'Lodge Sol',
            'ruc' => '20123456789', 'email' => 'lodge@test.pe', 'contact_name' => 'Ana', 'status' => 'pending',
        ]);
        $plan = SubscriptionPlan::create([
            'code' => 'basic-test', 'name' => 'Básico', 'monthly_price' => 300, 'currency' => 'PEN', 'is_active' => true,
        ]);
        Subscription::create([
            'organization_id' => $business->id, 'subscription_plan_id' => $plan->id, 'status' => 'pending',
            'monthly_price' => 300, 'currency' => 'PEN', 'starts_on' => today(),
        ]);

        $this->actingAs($admin)->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Bandeja de solicitudes')
            ->assertSee('Lodge Sol')
            ->assertSee('Pago pendiente')
            ->assertSee('Revisar pago y plan');
    }

    public function test_resolved_subscription_is_removed_from_pending_inbox(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $business = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Hotel Activo SAC', 'commercial_name' => 'Hotel Activo',
            'ruc' => '20987654321', 'email' => 'activo@test.pe', 'contact_name' => 'Luis', 'status' => 'active',
        ]);
        $plan = SubscriptionPlan::create([
            'code' => 'active-test', 'name' => 'Profesional', 'monthly_price' => 500, 'currency' => 'PEN', 'is_active' => true,
        ]);
        Subscription::create([
            'organization_id' => $business->id, 'subscription_plan_id' => $plan->id, 'status' => 'active',
            'monthly_price' => 500, 'currency' => 'PEN', 'starts_on' => today(), 'ends_on' => today()->addMonth(),
        ]);

        $this->actingAs($admin)->get(route('admin.requests.index'))
            ->assertOk()
            ->assertDontSee('Hotel Activo')
            ->assertSee('No hay solicitudes pendientes');
    }
}
