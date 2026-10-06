<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function departureWithSeats(): array
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Río Seguro SAC', 'ruc' => '20444444444', 'email' => 'rio@test.pe', 'status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Río Seguro', 'registration_number' => 'PA-9001', 'vessel_type' => 'lancha', 'seat_capacity' => 4, 'status' => 'ready']);
        $seatOne = VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1A', 'deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1]);
        $seatTwo = VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1B', 'deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 2]);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'IQT-NAU-02']);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 45, 'status' => 'scheduled']);

        return [$departure, $seatOne, $seatTwo];
    }

    public function test_guest_can_create_a_temporary_reservation_from_live_seat_map(): void
    {
        [$departure, $seatOne, $seatTwo] = $this->departureWithSeats();
        $this->get(route('bookings.create', $departure))->assertOk()->assertSee('1A')->assertSee('Croquis de la embarcación');
        $this->post(route('bookings.store', $departure), [
            'contact_name' => 'Ana Torres', 'contact_email' => 'ana@test.pe', 'contact_phone' => '999888777',
            'seats' => [$seatOne->id, $seatTwo->id],
            'passengers' => [['name' => 'Ana Torres', 'document' => '12345678'], ['name' => 'Luis Torres', 'document' => '87654321']],
        ])->assertRedirect();

        $reservation = Reservation::firstOrFail();
        $this->assertSame('pending_payment', $reservation->status);
        $this->assertSame('90.00', $reservation->total_amount);
        $this->assertDatabaseCount('reservation_seats', 2);
        $this->get(route('bookings.confirmation', $reservation->code))->assertOk()->assertSee($reservation->code)->assertSee('Ana Torres');
    }

    public function test_same_departure_seat_cannot_be_reserved_twice(): void
    {
        [$departure, $seatOne] = $this->departureWithSeats();
        $payload = ['contact_name' => 'Ana Torres', 'contact_email' => 'ana@test.pe', 'contact_phone' => '999888777', 'seats' => [$seatOne->id], 'passengers' => [['name' => 'Ana Torres', 'document' => '12345678']]];
        $this->post(route('bookings.store', $departure), $payload)->assertRedirect();
        $this->from(route('bookings.create', $departure))->post(route('bookings.store', $departure), $payload)->assertRedirect(route('bookings.create', $departure))->assertSessionHasErrors('seats');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_expired_pre_reservation_releases_seat_for_a_new_booking(): void
    {
        [$departure, $seatOne] = $this->departureWithSeats();
        $expired = Reservation::create(['route_departure_id' => $departure->id, 'code' => 'NY-EXPIRED', 'contact_name' => 'Anterior', 'contact_email' => 'old@test.pe', 'contact_phone' => '900', 'total_amount' => 45, 'status' => 'pending_payment', 'expires_at' => now()->subMinute()]);
        ReservationSeat::create(['reservation_id' => $expired->id, 'route_departure_id' => $departure->id, 'vessel_seat_id' => $seatOne->id, 'passenger_name' => 'Anterior', 'document_number' => '111']);
        $this->get(route('bookings.create', $departure))->assertOk();
        $this->assertDatabaseMissing('reservation_seats', ['reservation_id' => $expired->id]);
        $this->assertDatabaseHas('reservations', ['id' => $expired->id, 'status' => 'expired']);
    }
}
