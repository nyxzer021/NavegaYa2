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

class PaymentTicketTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(): Reservation
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Pagos SAC', 'ruc' => '20555555555', 'email' => 'pagos@test.pe', 'status' => 'active']);
        $a = Port::create(['name' => 'A', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $b = Port::create(['name' => 'B', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Pagador', 'registration_number' => 'PA-9100', 'vessel_type' => 'lancha', 'seat_capacity' => 5, 'status' => 'ready']);
        $seat = VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1A', 'deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1]);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $a->id, 'destination_port_id' => $b->id, 'code' => 'PAY-01']);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 50, 'status' => 'scheduled']);
        $reservation = Reservation::create(['route_departure_id' => $departure->id, 'code' => 'NY-PAYMENT', 'contact_name' => 'Pablo Pago', 'contact_email' => 'pablo@test.pe', 'contact_phone' => '999', 'total_amount' => 50, 'status' => 'pending_payment', 'expires_at' => now()->addMinutes(15)]);
        ReservationSeat::create(['reservation_id' => $reservation->id, 'route_departure_id' => $departure->id, 'vessel_seat_id' => $seat->id, 'passenger_name' => 'Pablo Pago', 'document_number' => '12345678']);

        return $reservation;
    }

    public function test_sandbox_payment_confirms_reservation_and_issues_ticket(): void
    {
        $reservation = $this->reservation();
        $this->get(route('payments.show', $reservation->code))->assertOk()->assertSee('Yape')->assertSee('Plin');
        $this->post(route('payments.sandbox-confirm', $reservation->code), ['method' => 'yape'])->assertRedirect(route('tickets.show', $reservation->code));
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('payments', ['reservation_id' => $reservation->id, 'method' => 'yape', 'status' => 'confirmed']);
        $this->assertDatabaseCount('tickets', 1);
        $this->get(route('tickets.show', $reservation->code))->assertOk()->assertSee('Pablo Pago')->assertSee('QR y código seguro de abordaje');
        $ticket = Ticket::firstOrFail();
        $this->get(route('tickets.qr', $ticket->code))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
        $this->get(route('tickets.verify', $ticket->boarding_token))->assertOk()->assertSee('Boleto válido');
        $admin = User::factory()->create();
        $organization = $reservation->departure->transportRoute->organization;
        $admin->roles()->attach(Role::create(['code' => 'company_admin', 'name' => 'Administrador empresa']), ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.boarding.board', $ticket))->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'boarded']);
        $this->get(route('tickets.verify', $ticket->boarding_token))->assertOk()->assertSee('Boleto ya utilizado');
    }

    public function test_sandbox_confirmation_is_unavailable_when_disabled(): void
    {
        config()->set('payments.sandbox_enabled', false);
        $reservation = $this->reservation();

        $this->get(route('payments.show', $reservation->code))
            ->assertOk()
            ->assertSee('Los pagos en línea todavía no están habilitados')
            ->assertDontSee('Confirmar pago de prueba');

        $this->post(route('payments.sandbox-confirm', $reservation->code), ['method' => 'yape'])
            ->assertNotFound();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'pending_payment']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_boarding_page_opens_the_selected_departure_roster_and_can_undo_boarding(): void
    {
        $reservation = $this->reservation();
        $seat = $reservation->seats()->firstOrFail();
        $ticket = Ticket::create([
            'reservation_seat_id' => $seat->id,
            'code' => 'BOL-BOARDING-01',
            'boarding_token' => 'BOARDING-TOKEN-01',
            'status' => 'confirmed',
            'issued_at' => now(),
        ]);
        $admin = User::factory()->create();
        $organization = $reservation->departure->transportRoute->organization;
        $admin->roles()->attach(Role::create(['code' => 'company_admin', 'name' => 'Administrador empresa']), ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        $this->actingAs($admin)
            ->get(route('admin.boarding.index', ['departure' => $reservation->route_departure_id]))
            ->assertOk()
            ->assertSee('Iquitos')
            ->assertSee('Nauta')
            ->assertSee('Pablo Pago')
            ->assertSee('BOL-BOARDING-01')
            ->assertSee('0 de 1 pasajeros a bordo');

        $this->actingAs($admin)->patch(route('admin.boarding.board', $ticket))->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'boarded']);
        $this->actingAs($admin)->patch(route('admin.boarding.board', $ticket))->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'confirmed', 'boarded_at' => null]);
    }

    public function test_company_boarding_is_scoped_to_the_selected_departure_and_organization(): void
    {
        $reservation = $this->reservation();
        $ticket = Ticket::create([
            'reservation_seat_id' => $reservation->seats()->firstOrFail()->id,
            'code' => 'BOL-COMPANY-01',
            'boarding_token' => 'COMPANY-TOKEN-01',
            'status' => 'confirmed',
            'issued_at' => now(),
        ]);
        $organization = $reservation->departure->transportRoute->organization;
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['code' => 'company_admin', 'name' => 'Administrador empresa']), ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        $this->actingAs($admin)
            ->get(route('company.boarding.show', $reservation->departure))
            ->assertOk()
            ->assertSee('Control de Embarque')
            ->assertSee('Pablo Pago')
            ->assertSee('Escanear DNI o Código QR');

        $this->actingAs($admin)
            ->patch(route('company.boarding.toggle', [$reservation->departure, $ticket]))
            ->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'boarded']);

        $otherCompany = Organization::create(['type' => 'transport_company', 'legal_name' => 'Otra SAC', 'ruc' => '20666666666', 'email' => 'otra@test.pe', 'status' => 'active']);
        $otherAdmin = User::factory()->create();
        $otherAdmin->roles()->attach($admin->roles()->firstOrFail()->id, ['organization_id' => $otherCompany->id]);
        $otherAdmin->organizations()->attach($otherCompany->id, ['status' => 'active']);

        $this->actingAs($otherAdmin)
            ->get(route('company.boarding.show', $reservation->departure))
            ->assertForbidden();
    }

    public function test_another_passenger_cannot_open_a_linked_reservation(): void
    {
        $owner = User::factory()->create();
        $otherPassenger = User::factory()->create();
        $reservation = $this->reservation();
        $reservation->update(['user_id' => $owner->id]);

        $this->actingAs($otherPassenger)
            ->get(route('payments.show', $reservation->code))
            ->assertForbidden();
    }
}
