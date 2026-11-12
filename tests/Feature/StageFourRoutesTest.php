<?php

namespace Tests\Feature;

use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageFourRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_route_keeps_stops_informative_and_sells_origin_to_destination(): void
    {
        [$admin, $company] = $this->company('Rutas Etapa', '20641000031', 'fluvial');
        [$origin, $destination, $stop] = $this->ports('fluvial');
        $master = $this->segment($origin, $destination, 'active');

        $this->actingAs($admin)->from(route('company.routes.index'))->post(route('company.routes.store'), [
            'master_route_id' => $master->id,
            'duration_hours' => 3,
            'duration_minutes' => 0,
            'stops' => [
                ['port_id' => $stop->id, 'hours' => 4, 'minutes' => 0],
            ],
        ])->assertRedirect(route('company.routes.index'))->assertSessionHasErrors('stops');
        $this->assertSame(0, $company->transportRoutes()->count());

        $this->actingAs($admin)->post(route('company.routes.store'), [
            'master_route_id' => $master->id,
            'duration_hours' => 3,
            'duration_minutes' => 0,
            'stops' => [
                ['port_id' => $stop->id, 'hours' => 1, 'minutes' => 30],
            ],
        ])->assertRedirect();

        $route = $company->transportRoutes()->first();
        $this->assertNotNull($route);
        $this->assertSame($master->id, $route->master_route_id);
        $this->assertSame(180, $route->estimated_duration_minutes);
        $this->assertDatabaseHas('route_stops', [
            'transport_route_id' => $route->id,
            'port_id' => $stop->id,
            'minutes_from_origin' => 90,
        ]);

        $vessel = Vessel::create([
            'organization_id' => $company->id,
            'name' => 'Lancha Ruta',
            'registration_number' => 'RUT-1',
            'vessel_type' => 'lancha',
            'seat_capacity' => 4,
            'status' => 'ready',
        ]);
        $seat = VesselSeat::create([
            'vessel_id' => $vessel->id,
            'code' => '1A',
            'deck' => 'principal',
            'row_position' => 1,
            'column_position' => 1,
        ]);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDay()->setTime(8, 0),
            'fare' => 50,
            'status' => 'scheduled',
            'is_published' => true,
        ]);

        $this->get(route('routes.index', ['origin' => 'Iquitos', 'destination' => 'Requena']))
            ->assertOk()
            ->assertSee('Escalas: Nauta 1 h 30 min')
            ->assertSee('Iquitos')
            ->assertSee('Requena');

        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-RUTA',
            'contact_name' => 'Ana',
            'contact_email' => 'ana@ruta.test',
            'contact_phone' => '999000111',
            'total_amount' => 50,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        $reservationSeat = ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Ana',
            'document_number' => '12345678',
        ]);
        Ticket::create([
            'reservation_seat_id' => $reservationSeat->id,
            'code' => 'BOL-RUTA',
            'boarding_token' => 'TOKENRUTA',
            'status' => 'confirmed',
            'issued_at' => now(),
        ]);

        $this->get(route('tickets.show', $reservation->code))
            ->assertOk()
            ->assertSee('Iquitos')
            ->assertSee('Requena')
            ->assertSee('Escalas: Nauta 1 h 30 min');
    }

    public function test_a_missing_segment_is_requested_and_decided_by_the_superadmin(): void
    {
        [$admin, $company] = $this->company('Solicita Tramo', '20641000032', 'fluvial');
        [$otherAdmin] = $this->company('Otra Ruta', '20641000033', 'fluvial');
        [$origin, $destination] = $this->ports('fluvial');
        $reviewer = $this->superAdmin();

        $this->actingAs($admin)->post(route('company.routes.request'), [
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'duration_hours' => 2,
            'duration_minutes' => 30,
        ])->assertRedirect();

        $segment = MasterRoute::query()->where('status', 'pending')->first();
        $this->assertNotNull($segment);
        $this->assertSame($company->id, $segment->requested_organization_id);
        $this->assertSame(150, $segment->estimated_duration_minutes);
        $this->assertNull($segment->rejection_reason);

        $this->actingAs($otherAdmin)->get(route('company.routes.index'))
            ->assertOk()
            ->assertDontSee('Iquitos → Requena');

        $this->actingAs($reviewer)->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Iquitos → Requena')
            ->assertSee('Solicitud de tramo');

        $this->actingAs($reviewer)->patch(route('admin.master-routes.reject', $segment), [
            'reason' => 'El muelle de destino no opera ese tramo',
        ])->assertRedirect();
        $this->assertSame('rejected', $segment->fresh()->status);

        $this->actingAs($admin)->get(route('company.routes.index'))
            ->assertOk()
            ->assertSee('El muelle de destino no opera ese tramo');

        $this->actingAs($admin)->post(route('company.routes.request'), [
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'duration_hours' => 2,
            'duration_minutes' => 0,
        ])->assertRedirect();
        $this->assertSame('pending', $segment->fresh()->status);
        $this->assertNull($segment->fresh()->rejection_reason);

        $this->actingAs($reviewer)->patch(route('admin.master-routes.approve', $segment))->assertRedirect();
        $this->assertSame('active', $segment->fresh()->status);

        $this->actingAs($admin)->post(route('company.routes.store'), [
            'master_route_id' => $segment->id,
            'duration_hours' => 2,
            'duration_minutes' => 0,
        ])->assertRedirect();
        $this->assertDatabaseHas('transport_routes', [
            'organization_id' => $company->id,
            'master_route_id' => $segment->id,
            'estimated_duration_minutes' => 120,
        ]);
    }

    public function test_a_company_can_edit_stops_and_cannot_deactivate_a_route_with_future_sales(): void
    {
        [$admin, $company] = $this->company('Edita Ruta', '20641000035', 'fluvial');
        [$otherAdmin] = $this->company('Ajena Ruta', '20641000036', 'fluvial');
        [$origin, $destination, $stop] = $this->ports('fluvial');
        $master = $this->segment($origin, $destination, 'active');

        $this->actingAs($admin)->post(route('company.routes.store'), [
            'master_route_id' => $master->id,
            'duration_hours' => 3,
            'duration_minutes' => 0,
        ])->assertRedirect();
        $route = $company->transportRoutes()->first();

        $this->actingAs($admin)->get(route('company.routes.index'))
            ->assertOk()
            ->assertSee('El pasaje se vende de origen a destino.')
            ->assertDontSee('etapa posterior')
            ->assertSee('Editar')
            ->assertSee('Desactivar');

        $this->actingAs($admin)->patch(route('company.routes.river.update', $route), [
            'duration_hours' => 4,
            'duration_minutes' => 0,
            'stops' => [
                ['port_id' => $stop->id, 'hours' => 2, 'minutes' => 0],
            ],
        ])->assertRedirect();
        $this->assertSame(240, $route->fresh()->estimated_duration_minutes);
        $this->assertDatabaseHas('route_stops', [
            'transport_route_id' => $route->id,
            'port_id' => $stop->id,
            'minutes_from_origin' => 120,
        ]);

        $this->actingAs($otherAdmin)->patch(route('company.routes.river.update', $route), [
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ])->assertNotFound();

        $vessel = Vessel::create([
            'organization_id' => $company->id,
            'name' => 'Lancha Edición',
            'registration_number' => 'EDI-1',
            'vessel_type' => 'lancha',
            'seat_capacity' => 4,
            'status' => 'ready',
        ]);
        $future = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDays(2)->setTime(8, 0),
            'fare' => 40,
            'status' => 'scheduled',
        ]);
        Reservation::create([
            'route_departure_id' => $future->id,
            'code' => 'NY-FUTURA',
            'contact_name' => 'Ana',
            'contact_email' => 'ana@edita.test',
            'contact_phone' => '999000222',
            'total_amount' => 40,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->followingRedirects()->from(route('company.routes.index'))
            ->patch(route('company.routes.river.toggle', $route))
            ->assertOk()
            ->assertSee('No se puede desactivar')
            ->assertSee($future->fresh()->code);
        $this->followRedirects = false;
        $this->assertSame('active', $route->fresh()->status);

        $unsold = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDays(4),
            'fare' => 40,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
        $future->update(['departure_at' => now()->subDay()]);
        $this->actingAs($admin)->patch(route('company.routes.river.toggle', $route))->assertRedirect();
        $this->assertSame('inactive', $route->fresh()->status);
        $this->assertFalse($unsold->fresh()->is_published);

        $this->actingAs($admin)->patch(route('company.routes.river.toggle', $route))->assertRedirect();
        $this->assertSame('active', $route->fresh()->status);
        $this->assertFalse($unsold->fresh()->is_published);
    }

    public function test_an_air_stop_uses_an_aerodrome_from_the_ports_table(): void
    {
        [$admin, $company] = $this->company('Ruta Aérea', '20641000034', 'aereo');
        [$origin, $destination, $stop] = $this->ports('aereo');
        $master = $this->segment($origin, $destination, 'active');

        $this->actingAs($admin)->post(route('company.routes.store'), [
            'master_route_id' => $master->id,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'stops' => [
                ['port_id' => $stop->id, 'hours' => 0, 'minutes' => 20],
            ],
        ])->assertRedirect();

        $route = $company->airRoutes()->first();
        $this->assertNotNull($route);
        $this->assertSame('Iquitos', $route->origin_city);
        $this->assertSame('Requena', $route->destination_city);
        $this->assertDatabaseHas('route_stops', [
            'air_route_id' => $route->id,
            'port_id' => $stop->id,
            'minutes_from_origin' => 20,
        ]);
        $this->actingAs($admin)->get(route('company.routes.index'))
            ->assertOk()
            ->assertSee('Escalas: Nauta 20 min');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $name, string $ruc, string $modality): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'commercial_name' => $name,
            'ruc' => $ruc,
            'email' => strtolower(str_replace(' ', '', $name)).'@test.pe',
            'status' => 'active',
            'modality' => $modality,
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

    /**
     * @return array{0: Port, 1: Port, 2: Port}
     */
    private function ports(string $modality): array
    {
        $origin = Port::create(['name' => 'Puerto Iquitos '.$modality, 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => $modality, 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto Requena '.$modality, 'city' => 'Requena', 'region' => 'Loreto', 'modality' => $modality, 'is_active' => true]);
        $stop = Port::create(['name' => 'Puerto Nauta '.$modality, 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => $modality, 'is_active' => true]);

        return [$origin, $destination, $stop];
    }

    private function segment(Port $origin, Port $destination, string $status): MasterRoute
    {
        return MasterRoute::create([
            'code' => 'TRM-'.$origin->id.'-'.$destination->id,
            'modality' => $origin->publicSide(),
            'origin_city' => $origin->city,
            'destination_city' => $destination->city,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_duration_minutes' => 180,
            'estimated_duration_text' => '3 h',
            'status' => $status,
        ]);
    }
}
