<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselMaintenancePeriod;
use App\Models\VesselSeat;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StageFourFleetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_rejects_a_maintenance_period_that_ends_before_it_starts(): void
    {
        [, $company] = $this->companyAdmin();
        $vessel = Vessel::create([
            'organization_id' => $company->id,
            'name' => 'Lancha Tope',
            'registration_number' => 'FLE-0',
            'vessel_type' => 'lancha',
            'seat_capacity' => 4,
            'status' => 'ready',
        ]);

        $this->expectException(QueryException::class);
        VesselMaintenancePeriod::create([
            'vessel_id' => $vessel->id,
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-01',
            'kind' => 'maintenance',
        ]);
    }

    public function test_maintenance_cannot_end_before_it_starts_and_blocks_scheduled_departures(): void
    {
        [$admin, $company] = $this->companyAdmin();
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Lancha Fecha', 'registration_number' => 'FLE-1', 'vessel_type' => 'lancha', 'seat_capacity' => 10, 'status' => 'ready']);
        $departure = $this->departure($company, $vessel, now()->addDays(3));

        $this->actingAs($admin)->post(route('company.fleet.vessels.maintenance.store', $vessel), [
            'starts_on' => now()->addDays(4)->toDateString(),
            'ends_on' => now()->addDays(2)->toDateString(),
            'kind' => 'maintenance',
        ])->assertSessionHasErrors('ends_on');
        $this->assertDatabaseCount('vessel_maintenance_periods', 0);

        $this->actingAs($admin)->post(route('company.fleet.vessels.maintenance.store', $vessel), [
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDays(4)->toDateString(),
            'kind' => 'repair',
        ])->assertSessionHasErrors('maintenance');
        $this->assertDatabaseCount('vessel_maintenance_periods', 0);
        $this->assertSame($departure->id, $departure->fresh()->id);

        $this->actingAs($admin)->post(route('company.fleet.vessels.maintenance.store', $vessel), [
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDays(4)->toDateString(),
            'kind' => 'repair',
            'resolutions' => [
                $departure->id => ['action' => 'reschedule', 'departure_at' => now()->addDays(8)->format('Y-m-d H:i'), 'reason' => 'Muelle en reparación'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('vessel_maintenance_periods', ['vessel_id' => $vessel->id, 'kind' => 'repair']);
        $this->assertTrue($departure->fresh()->departure_at->isSameDay(now()->addDays(8)));
        $this->assertSame('Muelle en reparación', $departure->fresh()->disruption_reason);
    }

    public function test_non_compliant_units_stay_visible_and_vests_are_checked_on_save(): void
    {
        [$admin, $company] = $this->companyAdmin();
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Lancha Corta', 'registration_number' => 'FLE-2', 'vessel_type' => 'lancha', 'seat_capacity' => 1, 'status' => 'ready']);
        VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1A', 'deck' => 'principal', 'row_position' => 1, 'column_position' => 1]);
        VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1B', 'deck' => 'principal', 'row_position' => 1, 'column_position' => 2]);

        $this->actingAs($admin)->get(route('company.fleet.index'))
            ->assertOk()
            ->assertSee('Lancha Corta')
            ->assertSee('Esta unidad no cumple la capacidad o los chalecos');

        $this->actingAs($admin)->patch(route('company.fleet.vessels.update', $vessel), [
            'name' => 'Lancha Corta',
            'registration_number' => 'FLE-2',
            'vessel_type' => 'lancha',
            'seat_capacity' => 2,
            'life_vest_count' => 1,
        ])->assertSessionHasErrors('life_vest_count');
    }

    public function test_admin_capacity_counts_only_enabled_units_and_suspension_keeps_the_unit(): void
    {
        $reviewer = $this->superAdmin();
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Flota Admin', 'commercial_name' => 'Flota Admin', 'ruc' => '20641000001', 'email' => 'flota-admin@test.pe', 'status' => 'active']);
        Vessel::create(['organization_id' => $company->id, 'name' => 'Habilitada', 'registration_number' => 'FLE-3', 'vessel_type' => 'lancha', 'seat_capacity' => 10, 'status' => 'ready']);
        Vessel::create(['organization_id' => $company->id, 'name' => 'Apagada', 'registration_number' => 'FLE-4', 'vessel_type' => 'lancha', 'seat_capacity' => 40, 'status' => 'inactive']);
        $aircraft = Aircraft::create(['organization_id' => $company->id, 'name' => 'Cessna', 'registration_number' => 'OB-FLE', 'aircraft_kind' => 'light_aircraft', 'seat_capacity' => 6, 'status' => 'ready']);

        $this->actingAs($reviewer)->get(route('admin.fleet-compliance.index'))
            ->assertOk()
            ->assertSee('Flota por validar')
            ->assertSee('Capacidad de las unidades habilitadas: 16 plazas');

        $this->actingAs($reviewer)->patch(route('admin.fleet-compliance.aircraft.suspend', $aircraft), [
            'reason' => 'Certificado de aeronavegabilidad vencido',
        ])->assertRedirect();

        $this->assertDatabaseHas('aircraft', ['id' => $aircraft->id, 'status' => 'suspended', 'suspended_by' => $reviewer->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'fleet_unit_suspended', 'subject_id' => $aircraft->id]);

        DB::table('users')->where('id', $reviewer->id)->delete();

        $this->assertNotNull(Aircraft::find($aircraft->id));
        $this->assertNull(Aircraft::find($aircraft->id)->suspended_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'fleet_unit_suspended', 'subject_id' => $aircraft->id, 'user_id' => null]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function companyAdmin(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Flota Etapa',
            'commercial_name' => 'Flota Etapa',
            'ruc' => '20641000002',
            'email' => 'flota-etapa@test.pe',
            'status' => 'active',
            'modality' => 'fluvial',
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadmin']));

        return $admin;
    }

    private function departure(Organization $organization, Vessel $vessel, $at): RouteDeparture
    {
        $origin = Port::create(['name' => 'Puerto A', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto B', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $master = MasterRoute::create([
            'code' => 'TRM-FLE-'.$organization->id,
            'modality' => 'fluvial',
            'origin_city' => 'Iquitos',
            'destination_city' => 'Nauta',
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'status' => 'active',
        ]);
        $route = TransportRoute::create([
            'organization_id' => $organization->id,
            'master_route_id' => $master->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'code' => 'FLE-RUTA',
        ]);

        return RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => $at,
            'fare' => 80,
            'status' => 'scheduled',
        ]);
    }
}
