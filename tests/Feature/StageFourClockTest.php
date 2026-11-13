<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Support\DepartureSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageFourClockTest extends TestCase
{
    use RefreshDatabase;

    public function test_eight_in_lima_is_stored_in_utc_and_shown_on_home_and_ticket(): void
    {
        [$admin, $organization] = $this->company('Reloj SAC', '20999000101');
        $departure = $this->river($organization, 'Lancha Reloj', 'Iquitos', 'Nauta');
        $when = DepartureSchedule::at(now()->timezone('America/Lima')->addDay()->toDateString(), '08:00');
        $departure->update(['departure_at' => $when]);

        $this->assertSame('13:00:00', $departure->fresh()->departure_at->utc()->format('H:i:s'));

        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()
            ->assertSee('08:00')
            ->assertDontSee('13:00');

        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-RELOJ',
            'contact_name' => 'Ana Reloj',
            'contact_email' => 'ana@test.pe',
            'contact_phone' => '999',
            'total_amount' => 80,
            'status' => 'confirmed',
        ]);
        $place = VesselSeat::create(['vessel_id' => $departure->vessel_id, 'code' => '1A', 'deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1]);
        $seat = ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $place->id,
            'passenger_name' => 'Ana Reloj',
            'document_number' => '44556677',
        ]);
        Ticket::create([
            'reservation_seat_id' => $seat->id,
            'code' => 'BOL-RELOJ',
            'boarding_token' => 'RELOJTOKEN',
            'status' => 'confirmed',
            'issued_at' => now(),
        ]);

        $this->get(route('tickets.show', $reservation->code))
            ->assertOk()
            ->assertSee('08:00')
            ->assertDontSee('13:00');
    }

    public function test_crew_assignment_includes_today_departure_that_already_left(): void
    {
        [$admin, $organization] = $this->company('Turno SAC', '20999000102');
        $open = $this->river($organization, 'Lancha Abierta', 'Iquitos', 'Nauta');
        $open->transportRoute->update(['estimated_duration_minutes' => 180]);
        $open->update(['departure_at' => now()->timezone('America/Lima')->subHours(2)]);
        $closed = $this->river($organization, 'Lancha Cerrada', 'Iquitos', 'Requena');
        $closed->transportRoute->update(['estimated_duration_minutes' => 30]);
        $closed->update(['departure_at' => now()->timezone('America/Lima')->subHours(6)]);

        $this->actingAs($admin)->get(route('company.crew.index'))
            ->assertOk()
            ->assertSee('Nauta')
            ->assertDontSee('Requena');
    }

    public function test_counter_uses_the_same_passenger_count_for_every_departure(): void
    {
        [$admin, $organization] = $this->company('Muelle SAC', '20999000103');
        $departure = $this->river($organization, 'Lancha Muelle', 'Iquitos', 'Nauta');
        $departure->vessel->update(['seat_capacity' => 24]);
        $departure->update(['departure_at' => now()->timezone('America/Lima')->setTime(8, 0)]);

        $this->actingAs($admin)->get(route('company.counter.index'))
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('0 de 24 pasajeros')
            ->assertDontSee('Portal Counter')
            ->assertDontSee('Río Normal')
            ->assertDontSee('Requiere implementación');
    }

    public function test_air_counter_warns_when_baggage_and_cargo_pass_the_useful_load(): void
    {
        [$admin, $organization] = $this->company('Aire SAC', '20999000104', 'aereo');
        $organization->update(['baggage_air_kg' => 10]);
        $aircraft = Aircraft::create([
            'organization_id' => $organization->id,
            'name' => 'Cessna',
            'registration_number' => 'OB-PESO',
            'seat_capacity' => 4,
            'useful_load_kg' => 50,
            'status' => 'ready',
        ]);
        $route = AirRoute::create([
            'organization_id' => $organization->id,
            'origin_city' => 'Iquitos',
            'destination_city' => 'Contamana',
            'code' => 'AIR-PESO',
            'estimated_duration_minutes' => 70,
        ]);
        $departure = AirDeparture::create([
            'air_route_id' => $route->id,
            'aircraft_id' => $aircraft->id,
            'departure_at' => DepartureSchedule::at(now()->timezone('America/Lima')->toDateString(), '09:00'),
            'fare' => 200,
            'status' => 'scheduled',
        ]);
        $reservation = Reservation::create([
            'air_departure_id' => $departure->id,
            'code' => 'NY-AIRE',
            'contact_name' => 'Luis Aire',
            'contact_email' => 'luis@test.pe',
            'contact_phone' => '999',
            'total_amount' => 200,
            'status' => 'confirmed',
        ]);
        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'air_departure_id' => $departure->id,
            'passenger_name' => 'Luis Aire',
            'document_number' => '12312312',
            'passenger_type' => 'adult',
        ]);
        CargoShipment::create([
            'air_departure_id' => $departure->id,
            'code' => 'GUIA-90',
            'sender_name' => 'Remitente Aire',
            'sender_phone' => '999',
            'recipient_name' => 'Destino',
            'description' => 'Caja',
            'package_count' => 1,
            'declared_weight_kg' => 80,
        ]);

        $this->actingAs($admin)->get(route('company.counter.index'))
            ->assertOk()
            ->assertSee('Se pasa de la carga útil');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $name, string $ruc, string $modality = 'fluvial'): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => $name,
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

    private function river(Organization $organization, string $vesselName, string $originCity, string $destinationCity): RouteDeparture
    {
        $origin = Port::create(['name' => 'Puerto '.$originCity.$vesselName, 'city' => $originCity, 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto '.$destinationCity.$vesselName, 'city' => $destinationCity, 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::create([
            'organization_id' => $organization->id,
            'name' => $vesselName,
            'registration_number' => 'MAT-'.substr(md5($vesselName), 0, 8),
            'vessel_type' => 'lancha',
            'seat_capacity' => 24,
            'life_vest_count' => 24,
            'status' => 'ready',
        ]);
        $route = TransportRoute::create([
            'organization_id' => $organization->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_duration_minutes' => 60,
            'code' => 'RUT-'.substr(md5($vesselName), 0, 6),
        ]);

        return RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDay(),
            'fare' => 80,
            'status' => 'scheduled',
        ]);
    }
}
