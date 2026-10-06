<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AircraftSeat;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\CheckoutOrder;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_can_reserve_multiple_air_seats_and_open_cart(): void
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Aero Amazonía SAC', 'ruc' => '20111111111', 'email' => 'aero@test.pe', 'status' => 'active']);
        $aircraft = Aircraft::create(['organization_id' => $company->id, 'name' => 'Cessna 208B', 'registration_number' => 'OB-AIR-01', 'seat_capacity' => 3, 'status' => 'ready']);
        $seats = collect(['1A', '1B'])->map(fn ($code, $index) => AircraftSeat::create(['aircraft_id' => $aircraft->id, 'code' => $code, 'row_position' => 1, 'column_position' => $index + 1, 'is_available' => true]));
        $route = AirRoute::create(['organization_id' => $company->id, 'origin_city' => 'Iquitos', 'destination_city' => 'Contamana', 'code' => 'AIR-TEST', 'estimated_duration_minutes' => 75]);
        $departure = AirDeparture::create(['air_route_id' => $route->id, 'aircraft_id' => $aircraft->id, 'departure_at' => now()->addDay(), 'fare' => 280, 'status' => 'scheduled']);

        $this->get(route('air-bookings.create', $departure))->assertOk()->assertSee('Elige tus asientos')->assertSee('1A');
        $response = $this->post(route('air-bookings.store', $departure), [
            'contact_name' => 'Daniel Reyna', 'contact_email' => 'daniel@example.com', 'contact_phone' => '999888777', 'contact_document' => '41694908',
            'seats' => $seats->pluck('id')->all(),
            'passengers' => [['name' => 'Daniel Reyna', 'document' => '41694908'], ['name' => 'Ana Ruiz', 'document' => '44556677']],
        ]);
        $reservation = Reservation::where('air_departure_id', $departure->id)->firstOrFail();
        $response->assertRedirect(route('cart.index'));
        $this->assertSame('560.00', $reservation->total_amount);
        $this->assertCount(2, $reservation->seats);
        $this->withSession(['cart_reservation_ids' => [$reservation->id]])->get(route('cart.index'))->assertOk()->assertSee('VIAJE AÉREO')->assertSee('Iquitos')->assertSee('Contamana');
        $this->withSession(['cart_reservation_ids' => [$reservation->id]])->post(route('cart.checkout'))->assertRedirect();
        $order = CheckoutOrder::firstOrFail();
        $this->withSession(['active_checkout_order' => $order->id])->get(route('cart.payment', $order->code))->assertOk()->assertSee('Iquitos')->assertSee('Contamana');
        $this->withSession(['active_checkout_order' => $order->id])->post(route('cart.sandbox-confirm', $order->code), ['method' => 'card'])->assertRedirect(route('cart.tickets', $order->code));
        $this->withSession(['active_checkout_order' => $order->id])->get(route('cart.tickets', $order->code))->assertOk()->assertSee('BOLETO AÉREO')->assertSee('Cessna 208B');
        $ticket = Ticket::firstOrFail();
        $this->withSession(['active_checkout_order' => $order->id])->get(route('tickets.pdf', $ticket->code))->assertOk();
    }
}
