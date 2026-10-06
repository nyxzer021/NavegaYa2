<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_profile_lands_in_its_scoped_portal(): void
    {
        $super = User::factory()->create();
        $super->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']));
        $this->actingAs($super)->get(route('dashboard'))->assertOk();

        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => 'Roles Loreto SAC', 'ruc' => '20999111222', 'email' => 'roles@example.test', 'status' => 'active']);
        $companyAdmin = User::factory()->create();
        $companyAdmin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']), ['organization_id' => $organization->id]);
        $companyAdmin->organizations()->attach($organization->id, ['status' => 'active']);
        $this->actingAs($companyAdmin)->get(route('dashboard'))->assertRedirect(route('company.departures.index'));

        $counter = User::factory()->create();
        $counter->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Counter']), ['organization_id' => $organization->id]);
        $counter->organizations()->attach($organization->id, ['status' => 'active']);
        $this->actingAs($counter)->get(route('dashboard'))->assertRedirect(route('company.counter.index'));

        $customer = User::factory()->create();
        $customer->roles()->attach(Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente / pasajero']));
        $this->actingAs($customer)->get(route('dashboard'))->assertRedirect(route('travels.index'));
        $this->actingAs($customer)->get(route('company.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('company.counter.index'))->assertForbidden();
    }

    public function test_verified_customer_claims_guest_purchase_and_only_sees_own_ticket(): void
    {
        [$ownedReservation, $ownedTicket] = $this->ticketPurchase('cliente@example.test', 'BOL-OWN-001', 'NY-OWN-001');
        [, $otherTicket] = $this->ticketPurchase('otra@example.test', 'BOL-OTHER-001', 'NY-OTHER-001');
        $customer = User::factory()->create(['email' => 'cliente@example.test', 'email_verified_at' => now()]);
        $customer->roles()->attach(Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente / pasajero']));

        $customer->claimGuestPurchases();

        $this->assertSame($customer->id, $ownedReservation->fresh()->user_id);
        $this->actingAs($customer)->get(route('travels.index'))
            ->assertOk()->assertSee('Mis boletos comprados')->assertSee($ownedTicket->code)->assertDontSee($otherTicket->code);
    }

    private function ticketPurchase(string $email, string $ticketCode, string $reservationCode): array
    {
        $suffix = substr(md5($ticketCode), 0, 6);
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => "Operador {$suffix}", 'ruc' => '20'.substr(preg_replace('/\D/', '', crc32($ticketCode)), 0, 9), 'email' => "{$suffix}@operator.test", 'status' => 'active']);
        $origin = Port::create(['name' => "Puerto {$suffix} A", 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => "Puerto {$suffix} B", 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => "Nave {$suffix}", 'registration_number' => "PA-{$suffix}", 'vessel_type' => 'rapida', 'seat_capacity' => 10, 'status' => 'ready']);
        $seat = VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1A', 'deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1]);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => "R-{$suffix}"]);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 95, 'status' => 'scheduled']);
        $reservation = Reservation::create(['route_departure_id' => $departure->id, 'code' => $reservationCode, 'contact_name' => 'Pasajero', 'contact_email' => $email, 'contact_phone' => '999999999', 'total_amount' => 95, 'status' => 'confirmed']);
        $reservationSeat = ReservationSeat::create(['reservation_id' => $reservation->id, 'route_departure_id' => $departure->id, 'vessel_seat_id' => $seat->id, 'passenger_name' => 'Pasajero', 'document_number' => '12345678']);
        $ticket = Ticket::create(['reservation_seat_id' => $reservationSeat->id, 'code' => $ticketCode, 'boarding_token' => strtoupper($suffix).$ticketCode, 'status' => 'confirmed', 'issued_at' => now()]);

        return [$reservation, $ticket];
    }
}
