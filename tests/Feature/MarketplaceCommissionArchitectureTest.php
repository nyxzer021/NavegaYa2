<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCommissionArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_commissions_without_operator_payout_controls(): void
    {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador']);
        $admin->roles()->attach($role);

        $this->actingAs($admin)
            ->get(route('admin.commissions.index'))
            ->assertOk()
            ->assertSee('Ingresos por cargo de servicio')
            ->assertSee('Sin fondos de operadores en custodia')
            ->assertDontSee('Registrar transferencia');
    }

    public function test_company_sees_its_commission_statement_without_payout_controls(): void
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Operador Marketplace SAC',
            'ruc' => '20999999991',
            'email' => 'operador@example.test',
            'status' => 'active',
            'commission_rate' => 7.5,
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador de empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        $response = $this->actingAs($admin)
            ->get(route('company.commissions.index'))
            ->assertOk()
            ->assertSee('Cargo de servicio NavegaYA: lo paga el viajero en la web. Tu tarifa se recibe completa.');
        $response->assertSee('href="'.route('company.sales.index').'" class="company-nav-link is-on"', false);
        $this->assertMatchesRegularExpression(
            '/Ventas y finanzas\s*<span[^>]*>›<\/span>\s*Historial de ventas\s*<span[^>]*>›<\/span>\s*<strong>Cargo de servicio<\/strong>/',
            $response->getContent(),
        );
        $response
            ->assertSee('Tarifa de tu empresa')
            ->assertSee('Cargos pagados por los viajeros')
            ->assertSee('7.50%')
            ->assertDontSee('Por cobrar')
            ->assertDontSee('Comisión generada')
            ->assertDontSee('Cuenta bancaria')
            ->assertDontSee('CCI');
    }

    public function test_company_statement_shows_the_fare_apart_from_the_commission(): void
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Operador Tarifa SAC',
            'ruc' => '20999999992',
            'email' => 'tarifa@example.test',
            'status' => 'active',
            'commission_rate' => 7.5,
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador de empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);
        $origin = Port::create(['name' => 'Origen tarifa', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Destino tarifa', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $organization->id, 'name' => 'Lancha tarifa', 'registration_number' => 'TAR-01', 'vessel_type' => 'lancha', 'seat_capacity' => 10, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'TAR-01']);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 100, 'status' => 'scheduled']);
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'RES-TARIFA',
            'contact_name' => 'Ana',
            'contact_email' => 'ana@viajero.test',
            'contact_phone' => '999000111',
            'base_amount' => 100,
            'discount_amount' => 0,
            'service_fee_amount' => 10,
            'total_amount' => 110,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        Payment::create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => 'yape',
            'provider' => 'sandbox',
            'provider_reference' => 'PAY-TARIFA',
            'amount' => 110,
            'commission_rate' => 7.5,
            'commission_amount' => 8.25,
            'operator_net' => 101.75,
            'commission_status' => 'pending',
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('company.sales.index'))
            ->assertOk()
            ->assertSee('Cargo de servicio pagado por los viajeros en el periodo: S/ 0.00 · Tu tarifa se recibe completa');

        $this->actingAs($admin)->get(route('company.commissions.index'))
            ->assertOk()
            ->assertSee('Comisiones anteriores al cambio')
            ->assertSee('S/ 100.00')
            ->assertSee('S/ 8.25')
            ->assertDontSee('Por cobrar')
            ->assertDontSee('Cuenta bancaria');
    }
}
