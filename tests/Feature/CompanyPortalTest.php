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

        $response = $this->actingAs($admin)->post(route('company.fleet.vessels.store'), [
            'name' => 'Rápida Segura', 'registration_number' => 'DICAPI-001', 'vessel_type' => 'rapido',
            'seat_capacity' => 24, 'base_port_id' => $port->id, 'organization_id' => $other->id,
        ]);
        $vessel = Vessel::where('name', 'Rápida Segura')->firstOrFail();
        $response->assertRedirect(route('company.vessels.seats.edit', $vessel));
        $this->assertDatabaseHas('vessels', ['name' => 'Rápida Segura', 'organization_id' => $organization->id]);
        $this->assertDatabaseMissing('vessels', ['name' => 'Rápida Segura', 'organization_id' => $other->id]);
        $this->actingAs($admin)->patch(route('company.finance.bank'), [
            'bank_name' => 'Banco de la Nación', 'bank_account' => '001-123456', 'bank_cci' => '01800112345678901234',
        ])->assertRedirect();
        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'bank_name' => 'Banco de la Nación']);
        $this->assertDatabaseHas('organizations', ['id' => $other->id, 'bank_name' => null]);
    }

    public function test_company_admin_can_open_the_fleet_page(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Flota', '20333330000');
        Vessel::create([
            'organization_id' => $organization->id, 'name' => 'Rápida Segura',
            'registration_number' => 'DICAPI-FLOTA', 'vessel_type' => 'rapido',
            'seat_capacity' => 24, 'status' => 'draft',
        ]);

        $this->actingAs($admin)->get(route('company.fleet.index'))
            ->assertOk()->assertSee('Rápida Segura')->assertSee('Cerrar sesión')->assertSee($admin->name);
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

    public function test_company_admin_can_reprogram_a_departure_with_traceability(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Contingencia', '20888888888');
        $departure = $this->departure($organization, 'Nave Operativa', 'Iquitos', 'Nauta');
        $originalDepartureAt = $departure->departure_at;
        $newDepartureAt = now()->addDays(2)->setTime(10, 30);

        $response = $this->actingAs($admin)->patch(route('company.departures.contingency', $departure), [
            'action' => 'reschedule',
            'departure_at' => $newDepartureAt->format('Y-m-d H:i:s'),
            'reason' => 'Cierre preventivo del puerto por seguridad.',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $departure->refresh();
        $this->assertSame('rescheduled', $departure->status);
        $this->assertSame($originalDepartureAt->format('Y-m-d H:i:s'), $departure->previous_departure_at->format('Y-m-d H:i:s'));
        $this->assertSame($newDepartureAt->format('Y-m-d H:i:s'), $departure->departure_at->format('Y-m-d H:i:s'));
        $this->assertSame($admin->id, $departure->status_changed_by_user_id);
        $this->assertSame('Cierre preventivo del puerto por seguridad.', $departure->disruption_reason);
    }

    public function test_company_admin_cannot_change_another_company_departure(): void
    {
        [$admin] = $this->companyAdmin('Operador Propio', '20999999991');
        [, $otherOrganization] = $this->companyAdmin('Operador Ajeno', '20999999992');
        $foreignDeparture = $this->departure($otherOrganization, 'Nave Ajena', 'Nauta', 'Lagunas');

        $response = $this->actingAs($admin)->patch(route('company.departures.contingency', $foreignDeparture), [
            'action' => 'cancel',
            'reason' => 'Intento no autorizado.',
        ]);

        $response->assertForbidden();
        $this->assertSame('scheduled', $foreignDeparture->fresh()->status);
    }

    public function test_company_cannot_sell_on_a_cancelled_departure(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Venta Segura', '20999999993');
        $departure = $this->departure($organization, 'Nave Cerrada', 'Iquitos', 'Pebas');
        $departure->update(['departure_at' => now()->setTime(15, 0), 'status' => 'cancelled', 'is_published' => false]);
        $seat = $departure->vessel->seats()->create(['code' => '1A', 'row_position' => 1, 'column_position' => 1, 'deck' => 1, 'is_available' => true]);

        $response = $this->actingAs($admin)->post(route('company.pos.store'), [
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'passenger_name' => 'Pasajero Seguro',
            'passenger_age' => 30,
            'phone' => '999999999',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('reservations', ['route_departure_id' => $departure->id]);
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
