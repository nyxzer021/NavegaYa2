<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\VerifyOrganizationContactEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminRequestInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_pending_affiliations_in_the_unified_inbox(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $business = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Transportes Sol SAC', 'commercial_name' => 'Transportes Sol',
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
            ->assertSee('Transportes Sol')
            ->assertSee('Validación pendiente')
            ->assertSee('Revisar empresa');
    }

    public function test_ordinary_pending_subscription_does_not_require_admin_review(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $business = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Hotel Pendiente SAC', 'commercial_name' => 'Hotel Pendiente',
            'ruc' => '20987654321', 'email' => 'activo@test.pe', 'contact_name' => 'Luis', 'status' => 'active',
        ]);
        $plan = SubscriptionPlan::create([
            'code' => 'pending-test', 'name' => 'Profesional', 'monthly_price' => 500, 'currency' => 'PEN', 'is_active' => true,
        ]);
        Subscription::create([
            'organization_id' => $business->id, 'subscription_plan_id' => $plan->id, 'status' => 'pending',
            'monthly_price' => 500, 'currency' => 'PEN', 'starts_on' => today(),
        ]);

        $this->actingAs($admin)->get(route('admin.requests.index'))
            ->assertOk()
            ->assertDontSee('Hotel Pendiente')
            ->assertSee('No hay solicitudes pendientes');
    }

    public function test_payment_exception_is_visible_in_the_inbox(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $business = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Lodge Excepción SAC', 'commercial_name' => 'Lodge Excepción',
            'ruc' => '20111111111', 'email' => 'excepcion@test.pe', 'contact_name' => 'Rosa', 'status' => 'active',
        ]);
        $plan = SubscriptionPlan::create([
            'code' => 'exception-test', 'name' => 'Básico', 'monthly_price' => 300, 'currency' => 'PEN', 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'organization_id' => $business->id, 'subscription_plan_id' => $plan->id, 'status' => 'pending',
            'monthly_price' => 300, 'currency' => 'PEN', 'starts_on' => today(),
        ]);
        Payment::create([
            'subscription_id' => $subscription->id, 'purpose' => 'subscription', 'method' => 'card', 'provider' => 'culqi',
            'provider_reference' => 'review-payment-1', 'amount' => 250, 'currency_code' => 'PEN', 'status' => 'review_required',
            'requires_review' => true, 'review_reason' => 'El monto confirmado no coincide con el plan.',
        ]);

        $this->actingAs($admin)->get(route('admin.requests.index', ['type' => 'payment']))
            ->assertOk()
            ->assertSee('Lodge Excepción')
            ->assertSee('El monto confirmado no coincide con el plan.');

        $this->actingAs($admin)->patch(route('admin.advertising-subscriptions.exceptions.resolve', Payment::first()), [
            'resolution' => 'reject',
            'reason' => 'El monto fue incorrecto y no corresponde aprobarlo.',
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', ['provider_reference' => 'review-payment-1', 'status' => 'rejected', 'requires_review' => false]);
        $this->actingAs($admin)->get(route('admin.requests.index', ['type' => 'payment']))
            ->assertDontSee('Lodge Excepción');
    }

    public function test_opening_the_inbox_marks_current_notifications_as_seen(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Transportes Pendientes SAC',
            'ruc' => '20123450001',
            'email' => 'pendiente@test.pe',
            'contact_name' => 'Rosa',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('<span data-unread-badge', false);
        $this->get(route('admin.requests.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertDontSee('<span data-unread-badge', false);
    }

    public function test_super_admin_can_resend_the_company_contact_verification(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $business = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Rutas Amazónicas SAC',
            'ruc' => '20123450002',
            'email' => 'contacto@rutas.test',
            'contact_name' => 'Ana',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.organizations.resend-verification', $business))
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentOnDemand(VerifyOrganizationContactEmail::class);
    }
}
