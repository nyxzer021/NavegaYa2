<?php

namespace Tests\Feature;

use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MasterRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_a_valid_master_route(): void
    {
        $admin = $this->userWithRole('super_admin');
        [$origin, $destination] = $this->ports();

        $this->actingAs($admin)->post(route('admin.master-routes.store'), [
            'code' => 'TRM-IQT-NAU', 'modality' => 'fluvial', 'origin_city' => 'Iquitos',
            'destination_city' => 'Nauta', 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
            'river_basin' => 'Río Amazonas / Marañón', 'corridor' => 'Corredor Loreto',
            'estimated_duration_text' => '1 h 45 min', 'status' => 'active',
            'path_geojson' => json_encode([
                'type' => 'LineString',
                'coordinates' => [[-73.2516, -3.7437], [-73.5757, -4.5051]],
            ]),
        ])->assertRedirect(route('admin.master-routes.index'));

        $this->assertDatabaseHas('master_routes', ['code' => 'TRM-IQT-NAU', 'status' => 'active']);
        $this->assertSame(
            [[-73.2516, -3.7437], [-73.5757, -4.5051]],
            MasterRoute::where('code', 'TRM-IQT-NAU')->firstOrFail()->path_geojson['coordinates'],
        );
        $this->actingAs($admin)->get(route('admin.master-routes.index'))->assertOk()->assertSee('Iquitos')->assertSee('Nauta');
    }

    public function test_master_route_rejects_invalid_geojson_coordinates(): void
    {
        $admin = $this->userWithRole('super_admin');
        [$origin, $destination] = $this->ports();

        $this->actingAs($admin)->post(route('admin.master-routes.store'), [
            'code' => 'TRM-GEO-BAD', 'modality' => 'fluvial', 'origin_city' => 'Iquitos',
            'destination_city' => 'Nauta', 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
            'status' => 'active', 'path_geojson' => json_encode([
                'type' => 'LineString',
                'coordinates' => [[-200, -3.7437]],
            ]),
        ])->assertSessionHasErrors(['path_geojson.coordinates', 'path_geojson.coordinates.0.0']);

        $this->assertDatabaseMissing('master_routes', ['code' => 'TRM-GEO-BAD']);
    }

    public function test_master_route_rejects_equal_origin_and_destination(): void
    {
        $admin = $this->userWithRole('super_admin');
        [$origin] = $this->ports();

        $this->actingAs($admin)->from(route('admin.master-routes.create'))->post(route('admin.master-routes.store'), [
            'code' => 'TRM-BAD', 'modality' => 'fluvial', 'origin_city' => 'Iquitos', 'destination_city' => 'Iquitos',
            'origin_port_id' => $origin->id, 'destination_port_id' => $origin->id, 'status' => 'active',
        ])->assertRedirect(route('admin.master-routes.create'))->assertSessionHasErrors(['destination_city', 'destination_port_id']);

        $this->assertDatabaseCount('master_routes', 0);
    }

    public function test_company_admin_can_only_schedule_from_an_active_master_route_for_its_own_vessel(): void
    {
        [$origin, $destination] = $this->ports();
        $masterRoute = MasterRoute::create([
            'code' => 'TRM-IQT-NAU', 'modality' => 'fluvial', 'origin_city' => 'Iquitos', 'destination_city' => 'Nauta',
            'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'estimated_duration_text' => '1 h 45 min', 'status' => 'active',
        ]);
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Amazonas SAC', 'ruc' => '20111111111', 'email' => 'amazonas@test.pe', 'status' => 'active']);
        $otherCompany = Organization::create(['type' => 'transport_company', 'legal_name' => 'Otra SAC', 'ruc' => '20222222222', 'email' => 'otra@test.pe', 'status' => 'active']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Río Azul', 'registration_number' => 'PA-7001', 'vessel_type' => 'lancha', 'seat_capacity' => 25, 'status' => 'ready']);
        $otherVessel = Vessel::create(['organization_id' => $otherCompany->id, 'name' => 'Ajena', 'registration_number' => 'PA-7002', 'vessel_type' => 'lancha', 'seat_capacity' => 20, 'status' => 'ready']);
        $companyAdmin = $this->userWithRole('company_admin', $company);

        $payload = ['master_route_id' => $masterRoute->id, 'vessel_id' => $vessel->id, 'departure_at' => '2026-10-01 08:00:00', 'boarding_starts_at' => '2026-10-01 07:30:00', 'fare' => '95.00'];
        $this->actingAs($companyAdmin)->post(route('company.departures.store'), $payload)->assertRedirect(route('company.departures.index'));

        $companyRoute = TransportRoute::firstOrFail();
        $this->assertSame($company->id, $companyRoute->organization_id);
        $this->assertSame($masterRoute->id, $companyRoute->master_route_id);
        $this->assertDatabaseHas('route_departures', ['transport_route_id' => $companyRoute->id, 'vessel_id' => $vessel->id, 'fare' => '95.00']);

        $this->actingAs($companyAdmin)->post(route('company.departures.store'), array_replace($payload, ['vessel_id' => $otherVessel->id]))->assertForbidden();
    }

    public function test_company_admin_cannot_use_a_suspended_master_route(): void
    {
        [$origin, $destination] = $this->ports();
        $route = MasterRoute::create(['code' => 'TRM-SUSP', 'modality' => 'fluvial', 'origin_city' => 'Iquitos', 'destination_city' => 'Nauta', 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'suspended_river_level']);
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Amazonas SAC', 'ruc' => '20333333333', 'email' => 'susp@test.pe', 'status' => 'active']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Río Azul', 'registration_number' => 'PA-7003', 'vessel_type' => 'lancha', 'seat_capacity' => 25, 'status' => 'ready']);
        $companyAdmin = $this->userWithRole('company_admin', $company);

        $this->actingAs($companyAdmin)->post(route('company.departures.store'), ['master_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => '2026-10-01 08:00:00', 'boarding_starts_at' => '2026-10-01 07:30:00', 'fare' => '95.00'])->assertNotFound();
        $this->assertDatabaseCount('route_departures', 0);
    }

    public function test_transport_route_model_rejects_ports_from_the_same_city(): void
    {
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => 'Ruta Segura SAC', 'ruc' => '20444444444', 'email' => 'ruta-segura@test.pe', 'status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Nauta A', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto Nauta B', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);

        $this->expectException(ValidationException::class);

        TransportRoute::create([
            'organization_id' => $organization->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
        ]);
    }

    private function userWithRole(string $code, ?Organization $organization = null): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['code' => $code], ['name' => $code]);
        $user->roles()->attach($role, ['organization_id' => $organization?->id]);
        if ($organization) {
            $user->organizations()->attach($organization->id, ['status' => 'active']);
        }

        return $user;
    }

    /** @return array{Port, Port} */
    private function ports(): array
    {
        return [
            Port::create(['name' => 'Puerto Silico', 'city' => 'Iquitos', 'region' => 'Loreto', 'latitude' => -3.7437, 'longitude' => -73.2516, 'is_active' => true]),
            Port::create(['name' => 'Muelle Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'latitude' => -4.5051, 'longitude' => -73.5757, 'is_active' => true]),
        ];
    }
}
