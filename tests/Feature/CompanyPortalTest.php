<?php

namespace Tests\Feature;

use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_departures_are_strictly_isolated_by_organization(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Uno', '20111111111');
        [, $other] = $this->companyAdmin('Operador Dos', '20222222222');
        $this->departure($organization, 'Nave Propia', 'Iquitos', 'Nauta');
        $this->departure($other, 'Nave Ajena', 'Yurimaguas', 'Lagunas');

        $this->actingAs($admin)->get(route('company.departures.index'))
            ->assertOk()->assertSee('Nave Propia')->assertSee('Iquitos')
            ->assertDontSee('Nave Ajena')->assertDontSee('Yurimaguas');
    }

    public function test_company_admin_registers_fleet_and_bank_data_only_for_its_tenant(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Seguro', '20333333333');
        [, $other] = $this->companyAdmin('Operador Ajeno', '20444444444');
        $port = Port::create(['name' => 'Puerto Silico', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);

        $this->actingAs($admin)->post(route('company.fleet.vessels.store'), [
            'name' => 'Rápida Segura', 'registration_number' => 'DICAPI-001', 'vessel_type' => 'rapido',
            'seat_capacity' => 24, 'base_port_id' => $port->id, 'organization_id' => $other->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('vessels', ['name' => 'Rápida Segura', 'organization_id' => $organization->id]);
        $this->assertDatabaseMissing('vessels', ['name' => 'Rápida Segura', 'organization_id' => $other->id]);

        $this->actingAs($admin)->patch(route('company.finance.bank'), [
            'bank_name' => 'Banco de la Nación', 'bank_account' => '001-123456', 'bank_cci' => '01800112345678901234',
        ])->assertRedirect();
        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'bank_name' => 'Banco de la Nación']);
        $this->assertDatabaseHas('organizations', ['id' => $other->id, 'bank_name' => null]);
    }

    public function test_company_manifest_is_available_only_for_its_own_departure(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Manifiesto', '20555555555');
        [, $other] = $this->companyAdmin('Operador Externo', '20666666666');
        $ownDeparture = $this->departure($organization, 'Nave DICAPI', 'Iquitos', 'Nauta');
        $foreignDeparture = $this->departure($other, 'Nave Externa', 'Nauta', 'Yurimaguas');

        $this->actingAs($admin)->get(route('company.departures.manifest', $ownDeparture))
            ->assertOk()
            ->assertSee('Manifiesto oficial de pasajeros')
            ->assertSee('Nave DICAPI');
        $this->actingAs($admin)->get(route('company.departures.manifest', $foreignDeparture))->assertForbidden();
    }

    public function test_company_admin_can_open_sales_dashboard_and_export_its_period(): void
    {
        [$admin] = $this->companyAdmin('Operador Comercial', '20777777777');

        $this->actingAs($admin)->get(route('company.sales.index'))
            ->assertOk()
            ->assertSee('Ventas e ingresos de Operador Comercial')
            ->assertSee('Recaudación total')
            ->assertSee('Evolución de ingresos');

        $this->actingAs($admin)->get(route('company.sales.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function companyAdmin(string $name, string $ruc): array
    {
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => $name, 'ruc' => $ruc, 'email' => strtolower(str_replace(' ', '', $name)).'@test.pe', 'status' => 'active']);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function departure(Organization $organization, string $vesselName, string $originCity, string $destinationCity): RouteDeparture
    {
        $origin = Port::create(['name' => 'Puerto '.$originCity.$organization->id, 'city' => $originCity, 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto '.$destinationCity.$organization->id, 'city' => $destinationCity, 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::create(['organization_id' => $organization->id, 'name' => $vesselName, 'registration_number' => 'MAT-'.$organization->id, 'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready']);
        $masterRoute = MasterRoute::create([
            'code' => 'TRM-TEST-'.$organization->id,
            'modality' => 'fluvial',
            'origin_city' => $originCity,
            'destination_city' => $destinationCity,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'status' => 'active',
        ]);
        $route = TransportRoute::create(['organization_id' => $organization->id, 'master_route_id' => $masterRoute->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id]);

        return RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 95, 'status' => 'scheduled']);
    }
}
