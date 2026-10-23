<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vessel;
use Database\Seeders\MarketplaceDashboardSeeder;
use Database\Seeders\RegionalSupervisionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_governance_modules(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Panorama corporativo')
            ->assertSee('Salidas del día')
            ->assertSee('Realizadas')
            ->assertSee('Pendientes')
            ->assertSee('Reprogramadas')
            ->assertSee('Supervisión de Itinerarios')
            ->assertSee('Rendimiento financiero por operador')
            ->assertSee('Publicidad y anunciantes')
            ->assertSee('Auditoría de Actividad')
            ->assertSee('Menú y Páginas');
        $this->actingAs($admin)->get(route('admin.companies.index'))->assertOk()->assertSee('Red de empresas transportistas');
        $this->get(route('admin.itineraries.index'))->assertOk()->assertSee('Supervisión de itinerarios');
        $this->get(route('admin.fleet.index'))->assertRedirect(route('admin.companies.index'));
        $this->get(route('admin.settlements.index'))->assertRedirect(route('admin.commissions.index'));
        $this->get(route('admin.commissions.index'))->assertOk()->assertSee('Comisiones y Facturación');
        $this->get(route('admin.sales.index'))->assertOk();
        $this->get(route('admin.ports.index'))->assertOk();
        $this->get(route('admin.master-routes.index'))->assertOk();
        $this->get(route('admin.tourism-partners.index'))->assertOk()->assertSee('Negocios, fichas y moderación');
        $this->get(route('admin.advertisements.index'))->assertOk()->assertSee('Publicidad y pautas B2B');
        $this->get(route('admin.refunds.index'))->assertOk()->assertSee('Reembolsos e incidencias de pago');
        $this->get('/admin/carga-general')->assertRedirect(route('admin.cargo.index'));
    }

    public function test_commercial_dashboard_uses_paid_marketplace_data(): void
    {
        $this->seed(MarketplaceDashboardSeeder::class);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('S/ 490.00')
            ->assertSee('S/ 39.20')
            ->assertSee('Amazonía Express')
            ->assertSee('Selva Air Taxi')
            ->assertSee('Expreso Fluvial Marañón');

        $this->assertDatabaseHas('payments', ['provider' => 'culqi', 'status' => 'succeeded', 'amount' => 280]);
        $this->assertDatabaseHas('organizations', ['ruc' => '20608912341', 'status' => 'pending_verification']);
    }

    public function test_commercial_dashboard_supports_period_filters(): void
    {
        $this->seed(MarketplaceDashboardSeeder::class);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)->get(route('admin.dashboard', ['period' => 'month']))
            ->assertOk()
            ->assertSee('S/ 490.00')
            ->assertSee('Este mes');

        $this->get(route('admin.dashboard', ['period' => 'week']))
            ->assertOk()
            ->assertSee('7 días');
    }

    public function test_daily_dashboard_uses_six_time_blocks_and_reports_daily_commission(): void
    {
        $this->seed(MarketplaceDashboardSeeder::class);
        $admin = $this->userWithRole('super_admin');
        $organization = Organization::where('status', 'active')->firstOrFail();
        $plan = SubscriptionPlan::create([
            'code' => 'B2B-DASHBOARD-TEST',
            'name' => 'Pauta mensual de prueba',
            'monthly_price' => 300,
            'currency' => 'PEN',
            'is_active' => true,
        ]);
        Subscription::create([
            'organization_id' => $organization->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'monthly_price' => 300,
            'currency' => 'PEN',
            'starts_on' => today()->subDay(),
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard', ['period' => 'today']))
            ->assertOk()
            ->assertSee('S/ 39.20')
            ->assertSeeTextInOrder(['00:00', '04:00', '08:00', '12:00', '16:00', '20:00']);
    }

    public function test_dashboard_ignores_subscription_payments_without_reservations(): void
    {
        $admin = $this->userWithRole('super_admin');
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Empresa Suscriptora SAC', 'commercial_name' => 'Empresa Suscriptora',
            'ruc' => '20666666666', 'email' => 'suscriptora@test.pe', 'contact_name' => 'Rosa', 'status' => 'active',
        ]);
        $plan = SubscriptionPlan::create([
            'code' => 'dashboard-subscription', 'name' => 'Básico', 'monthly_price' => 300, 'currency' => 'PEN', 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => 'active',
            'monthly_price' => 300, 'currency' => 'PEN', 'starts_on' => today(), 'ends_on' => today()->addMonth(),
        ]);
        Payment::create([
            'subscription_id' => $subscription->id, 'purpose' => 'subscription', 'method' => 'card', 'provider' => 'culqi',
            'provider_reference' => 'subscription-dashboard-payment', 'amount' => 300, 'currency_code' => 'PEN',
            'status' => 'confirmed', 'paid_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_configuration_modules_use_focused_subtabs(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)->get(route('admin.navigation.index', ['tab' => 'menu']))
            ->assertOk()->assertSee('Pestañas actuales')->assertDontSee('Páginas creadas');
        $this->get(route('admin.users.index', ['tab' => 'create']))
            ->assertOk()->assertSee('Crear administrador')->assertDontSee('Usuarios registrados');
        $this->get(route('admin.settings.index', ['tab' => 'payments']))
            ->assertOk()->assertSee('Comisión NavegaYA')->assertDontSee('Mensaje inicial');
    }

    public function test_user_access_screen_only_lists_platform_administrators_and_explains_menu_access(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $platformAdmin = $this->userWithRole('admin');
        $platformAdmin->update(['name' => 'Administrador interno']);
        $traveler = $this->userWithRole('traveler');
        $traveler->update(['name' => 'Viajero externo']);

        $this->actingAs($superAdmin)->get(route('admin.users.index', ['tab' => 'manage']))
            ->assertOk()
            ->assertSee('Administrador interno')
            ->assertDontSee('Viajero externo');
        $this->get(route('admin.users.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('Rutas y corredores')
            ->assertSee('Revisión de flota')
            ->assertSee('Integraciones y ajustes');
    }

    public function test_company_admin_cannot_open_super_admin_governance(): void
    {
        $companyAdmin = $this->userWithRole('company_admin');

        $this->actingAs($companyAdmin)->get(route('admin.itineraries.index'))->assertForbidden();
    }

    public function test_super_admin_can_open_company_creation_route(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin);
        $this->assertTrue(Route::has('admin.companies.create'));
        $this->assertTrue(Route::has('admin.companies.store'));
        $this->get(route('admin.companies.create'))->assertOk()->assertSee('Nueva empresa de transporte');
    }

    public function test_regional_supervision_combines_river_and_air_operators(): void
    {
        $this->seed(RegionalSupervisionSeeder::class);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)->get(route('admin.itineraries.index'))
            ->assertOk()
            ->assertSee('Supervisión de itinerarios')
            ->assertSee('Expreso Fluvial Marañón')
            ->assertSee('Selva Air Taxi')
            ->assertSee('Pausar ventas')
            ->assertSee('Salidas publicadas')
            ->assertSee('Asientos disponibles')
            ->assertSee('Salidas del día')
            ->assertSee('Salidas realizadas')
            ->assertSee('Pendientes')
            ->assertSee('Canceladas')
            ->assertSee('Reprogramadas')
            ->assertDontSee('DICAPI')
            ->assertDontSee('DGAC')
            ->assertDontSee('Editar');

        $this->get(route('admin.itineraries.index', ['tab' => 'fluvial']))
            ->assertOk()
            ->assertSee('Operación fluvial')
            ->assertSee('Operador fluvial')
            ->assertDontSee('Operación aérea');

        $this->get(route('admin.itineraries.index', ['tab' => 'aereo']))
            ->assertOk()
            ->assertSee('Operación aérea')
            ->assertSee('Operador aéreo')
            ->assertDontSee('Operación fluvial');

        Organization::where('ruc', '20443322110')->update(['is_marketplace_paused' => true]);
        $this->get(route('admin.itineraries.index', ['status' => 'paused']))
            ->assertOk()
            ->assertSee('Selva Air Taxi')
            ->assertDontSee('Expreso Fluvial Marañón');

        $this->get(route('admin.supervision.index'))->assertRedirect(route('admin.itineraries.index'));

        $this->assertDatabaseHas('organizations', ['ruc' => '20556677889', 'base_city' => 'Nauta']);
        $this->assertDatabaseHas('organizations', ['ruc' => '20443322110', 'base_city' => 'Iquitos']);
    }

    public function test_super_admin_can_edit_a_departure_without_crossing_company_boundaries(): void
    {
        $this->seed(RegionalSupervisionSeeder::class);
        $admin = $this->userWithRole('super_admin');
        $departure = RouteDeparture::with(['transportRoute.originPort', 'transportRoute.destinationPort'])->firstOrFail();
        $route = $departure->transportRoute;
        $masterRoute = MasterRoute::create([
            'code' => 'TRM-TEST-NAU-REQ',
            'modality' => 'fluvial',
            'origin_city' => $route->originPort->city,
            'destination_city' => $route->destinationPort->city,
            'origin_port_id' => $route->origin_port_id,
            'destination_port_id' => $route->destination_port_id,
            'estimated_duration_text' => '5 h 0 min',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('admin.itineraries.edit', ['departure' => $departure->id, 'type' => 'fluvial']))
            ->assertOk()
            ->assertSee('Editar salida fluvial')
            ->assertSee('Expreso Fluvial Marañón');

        $newDepartureAt = now()->addDay()->setTime(16, 0)->seconds(0);
        $this->put(route('admin.supervision.update', $departure->id), [
            'type' => 'fluvial',
            'master_route_id' => $masterRoute->id,
            'vessel_id' => $departure->vessel_id,
            'departure_time' => $newDepartureAt->format('Y-m-d H:i:s'),
            'price' => 149.90,
            'status' => 'boarding',
        ])->assertRedirect(route('admin.supervision.index'));

        $this->assertDatabaseHas('route_departures', [
            'id' => $departure->id,
            'vessel_id' => $departure->vessel_id,
            'fare' => 149.90,
            'status' => 'boarding',
        ]);

        $foreignVessel = Vessel::create([
            'organization_id' => Organization::where('ruc', '20443322110')->value('id'),
            'name' => 'Nave externa',
            'registration_number' => 'EXT-001',
            'vessel_type' => 'Rápida',
            'seat_capacity' => 10,
            'status' => 'ready',
        ]);

        $this->put(route('admin.supervision.update', $departure->id), [
            'type' => 'fluvial',
            'master_route_id' => $masterRoute->id,
            'vessel_id' => $foreignVessel->id,
            'departure_time' => $newDepartureAt->format('Y-m-d H:i:s'),
            'price' => 149.90,
            'status' => 'boarding',
        ])->assertNotFound();
    }

    public function test_super_admin_can_pause_a_departure_without_cancelling_the_operator_trip(): void
    {
        $this->seed(RegionalSupervisionSeeder::class);
        $admin = $this->userWithRole('super_admin');
        $departure = RouteDeparture::firstOrFail();

        $this->actingAs($admin)->patch(route('admin.itineraries.publication', [
            'departure' => $departure->id,
            'type' => 'fluvial',
        ]))->assertRedirect();

        $this->assertDatabaseHas('route_departures', [
            'id' => $departure->id,
            'status' => 'scheduled',
            'is_published' => false,
        ]);

        $this->get(route('routes.index'))->assertOk()->assertDontSee('Expreso Fluvial Marañón');
    }

    public function test_super_admin_can_pause_all_web_sales_for_an_operator(): void
    {
        $this->seed(RegionalSupervisionSeeder::class);
        $admin = $this->userWithRole('super_admin');
        $organization = Organization::where('ruc', '20556677889')->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.itineraries.operator-sales', $organization))
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_marketplace_paused' => true,
        ]);
        $this->get(route('routes.index'))->assertOk()->assertDontSee('Expreso Fluvial Marañón');

        $this->patch(route('admin.itineraries.operator-sales', $organization))->assertRedirect();
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'is_marketplace_paused' => false,
        ]);
    }

    public function test_super_admin_can_audit_and_update_both_fleet_modalities(): void
    {
        $this->seed(RegionalSupervisionSeeder::class);
        $admin = $this->userWithRole('super_admin');

        $this->actingAs($admin)->get(route('admin.fleet.index'))
            ->assertRedirect(route('admin.companies.index'));

        $vessel = Vessel::firstOrFail();
        $aircraft = Aircraft::firstOrFail();

        $this->patch(route('admin.fleet.vessels.status', $vessel), ['status' => 'maintenance'])->assertRedirect();
        $this->patch(route('admin.fleet.aircraft.status', $aircraft), ['status' => 'inactive'])->assertRedirect();

        $this->assertDatabaseHas('vessels', ['id' => $vessel->id, 'status' => 'maintenance']);
        $this->assertDatabaseHas('aircraft', ['id' => $aircraft->id, 'status' => 'inactive']);
    }

    public function test_company_operation_count_opens_an_isolated_operator_dossier(): void
    {
        $this->seed(RegionalSupervisionSeeder::class);
        $admin = $this->userWithRole('super_admin');
        $riverOperator = Organization::where('ruc', '20556677889')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.companies.index', ['tab' => 'rendimiento']))
            ->assertOk()
            ->assertSee('registrada(s)')
            ->assertDontSee('Ver dossier');

        $this->get(route('admin.companies.show', $riverOperator))
            ->assertOk()
            ->assertSee('Dossier del operador')
            ->assertSee('Expreso Fluvial Marañón')
            ->assertSee('Rápido Marañón II')
            ->assertDontSee('Selva Caravan I');

        $this->get(route('admin.companies.show', $riverOperator))
            ->assertOk()
            ->assertSee('Supervisión de embarcaciones fluviales')
            ->assertSee('Revisión DICAPI pendiente');
    }

    public function test_super_admin_can_create_an_access_account_for_an_existing_company(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Amazonía Express SAC',
            'commercial_name' => 'Amazonía Express',
            'ruc' => '20987654321',
            'email' => 'contacto@amazonia.test',
            'phone' => '999888777',
            'status' => 'active',
        ]);

        $this->actingAs($superAdmin)->put(route('admin.companies.update', $organization), [
            'legal_name' => $organization->legal_name,
            'commercial_name' => $organization->commercial_name,
            'phone' => $organization->phone,
            'base_city' => 'Iquitos',
            'modality' => 'fluvial',
            'commission_rate' => 8,
            'commercial_plan' => 'standard',
            'admin_name' => 'Administrador Amazonía',
            'admin_email' => 'admin@amazonia.test',
            'admin_password' => 'Temporal123!',
            'admin_password_confirmation' => 'Temporal123!',
        ])->assertRedirect(route('admin.companies.index'));

        $companyAdmin = User::where('email', 'admin@amazonia.test')->firstOrFail();
        $this->assertTrue(Hash::check('Temporal123!', $companyAdmin->password));
        $this->assertTrue($companyAdmin->organizations()->whereKey($organization->id)->exists());
        $this->assertTrue($companyAdmin->roles()->where('code', 'company_admin')->exists());
    }

    private function userWithRole(string $code): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['code' => $code], ['name' => $code]);
        $user->roles()->attach($role);

        return $user;
    }
}
