<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Discount;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(17, 0));
    }

    public function test_cash_sale_records_the_change_and_charges_no_service_fee(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Caja', '20600000001');
        $organization->update(['commission_rate' => 8, 'physical_sales_commission_rate' => 5]);
        $departure = $this->departure($organization, 'Nave Caja');
        $seat = $this->seat($departure, '1A');
        $site = OrganizationSite::create([
            'organization_id' => $organization->id,
            'name' => 'Muelle de Iquitos',
            'kind' => 'dock',
            'address' => 'Malecón Tarapacá',
            'city' => 'Iquitos',
            'is_active' => true,
        ]);

        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'site_id' => $site->id,
            'method' => 'cash',
            'cash_tendered' => 100,
        ]))->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->assertSame('cash', $payment->method);
        $this->assertSame('95.00', $payment->amount);
        $this->assertSame('100.00', $payment->cash_tendered);
        $this->assertSame('5.00', $payment->cash_change);
        $this->assertSame('0.00', $payment->commission_rate);
        $this->assertSame('0.00', $payment->commission_amount);
        $reservation = Reservation::firstOrFail();
        $this->assertSame('0.00', $reservation->service_fee_amount);
        $this->assertSame('counter', $reservation->sales_channel);
        $this->assertSame($seller->id, $reservation->sold_by_user_id);
        $this->assertSame($site->id, $reservation->organization_site_id);
        $this->assertNotNull($reservation->seats()->first()?->ticket);
    }

    public function test_cash_below_the_total_is_rejected(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Corto', '20600000002');
        $departure = $this->departure($organization, 'Nave Corta');
        $seat = $this->seat($departure, '1A');

        $this->actingAs($seller)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'method' => 'cash',
            'cash_tendered' => 10,
        ]))->assertRedirect()->assertSessionHasErrors('cash_tendered');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_yape_without_an_operation_number_is_rejected(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Yape', '20600000003');
        $departure = $this->departure($organization, 'Nave Yape');
        $seat = $this->seat($departure, '1A');

        $this->actingAs($seller)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'method' => 'yape',
        ]))->assertRedirect()->assertSessionHasErrors('operation_number');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_an_occupied_seat_and_an_agency_hold_cannot_be_sold(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Plaza', '20600000004');
        $departure = $this->departure($organization, 'Nave Plaza');
        $taken = $this->seat($departure, '1A');
        $held = $this->seat($departure, '1B', 2);
        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $taken->id,
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertRedirect();

        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $taken->id,
            'document_number' => '87654321',
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertRedirect()->assertSessionHasErrors('seat_id');
        RouteDepartureSeatHold::create([
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $held->id,
            'kind' => 'agency',
        ]);
        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $held->id,
            'document_number' => '11223344',
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertRedirect()->assertSessionHasErrors('seat_id');

        $this->assertSame(1, Reservation::query()->where('status', 'confirmed')->count());
        $this->assertDatabaseHas('route_departure_seat_holds', ['vessel_seat_id' => $held->id]);
    }

    public function test_a_cancelled_departure_cannot_be_sold(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Cerrada', '20600000005');
        $departure = $this->departure($organization, 'Nave Cerrada');
        $departure->update(['status' => 'cancelled']);
        $seat = $this->seat($departure, '1A');

        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertUnprocessable();

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_voiding_frees_the_seat_and_keeps_the_cash_payment(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Anula', '20600000006');
        $departure = $this->departure($organization, 'Nave Anula');
        $seat = $this->seat($departure, '1A');
        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'method' => 'cash',
            'cash_tendered' => 100,
        ]))->assertRedirect();
        $reservation = Reservation::firstOrFail();

        $this->actingAs($seller)->post(route('company.pos.void', $reservation), [
            'reason' => 'El pasajero no viaja',
        ])->assertRedirect(route('company.pos.index'));

        $reservation->refresh();
        $this->assertSame('cancelled', $reservation->status);
        $this->assertSame('El pasajero no viaja', $reservation->void_reason);
        $this->assertNull($reservation->seats()->first()?->vessel_seat_id);
        $this->assertSame('voided', $reservation->payments()->first()?->status);
        $this->assertSame('cash', $reservation->payments()->first()?->method);
        $this->assertSame('100.00', $reservation->payments()->first()?->cash_tendered);
        $this->assertSame('voided', Ticket::first()?->status);
        $this->assertTrue(AuditLog::query()->where('event', 'counter_sale_voided')->exists());
    }

    public function test_another_seller_cannot_void_and_an_admin_can(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Dueño', '20600000007');
        $other = User::factory()->create();
        $other->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $organization->id]);
        $other->organizations()->attach($organization->id, ['status' => 'active']);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);
        $departure = $this->departure($organization, 'Nave Ajena');
        $seat = $this->seat($departure, '1A');
        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'method' => 'card',
            'operation_number' => 'OP-44',
        ]))->assertRedirect();
        $reservation = Reservation::firstOrFail();

        $this->actingAs($other)->post(route('company.pos.void', $reservation), [
            'reason' => 'No me corresponde',
        ])->assertForbidden();
        $this->assertSame('confirmed', $reservation->fresh()->status);

        $this->actingAs($admin)->post(route('company.pos.void', $reservation), [
            'reason' => 'El administrador anula',
        ])->assertRedirect();
        $this->assertSame('cancelled', $reservation->fresh()->status);
    }

    public function test_another_company_cannot_sell_or_void_the_departure(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Uno', '20600000008');
        [$intruder] = $this->seller('Vendedor Dos', '20600000009');
        $departure = $this->departure($organization, 'Nave Privada');
        $seat = $this->seat($departure, '1A');
        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'method' => 'yape',
            'operation_number' => 'YP-9',
        ]))->assertRedirect();
        $reservation = Reservation::firstOrFail();

        $this->actingAs($intruder)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertForbidden();
        $this->actingAs($intruder)->post(route('company.pos.void', $reservation), [
            'reason' => 'Intento ajeno',
        ])->assertForbidden();
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_a_boarded_ticket_cannot_be_voided(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Embarque', '20600000010');
        $departure = $this->departure($organization, 'Nave Embarque');
        $seat = $this->seat($departure, '1A');
        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertRedirect();
        Ticket::query()->update(['status' => 'boarded']);
        $reservation = Reservation::firstOrFail();

        $this->actingAs($seller)->from(route('tickets.show', $reservation->code))->post(route('company.pos.void', $reservation), [
            'reason' => 'Ya embarcó',
        ])->assertRedirect()->assertSessionHasErrors('reason');

        $this->assertSame($seat->id, ReservationSeat::first()?->vessel_seat_id);
        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_a_counter_discount_reduces_the_fare(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Descuento', '20600000011');
        $departure = $this->departure($organization, 'Nave Descuento');
        $seat = $this->seat($departure, '1A');
        $discount = Discount::create([
            'organization_id' => $organization->id,
            'name' => 'Niño del muelle',
            'kind' => 'percent',
            'value' => 10,
            'channels' => ['counter'],
            'status' => 'active',
        ]);

        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'discount_id' => $discount->id,
            'method' => 'cash',
            'cash_tendered' => 85.50,
        ]))->assertRedirect();

        $this->assertSame('85.50', Reservation::firstOrFail()->total_amount);
        $this->assertSame('9.50', Reservation::firstOrFail()->discount_amount);
        $this->assertSame(1, $discount->fresh()->redeemed_count);
    }

    public function test_the_desk_lists_today_and_can_open_the_week(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Semana', '20600000012');
        $today = $this->departure($organization, 'Nave Hoy');
        $today->update(['departure_at' => now()->addHours(4)]);
        $this->seat($today, '1A');
        $later = $this->departure($organization, 'Nave Luego');
        $later->update(['departure_at' => now()->addDays(2)]);
        $this->seat($later, '1A');

        $this->actingAs($seller)->get(route('company.pos.index'))
            ->assertOk()
            ->assertSee('Nave Hoy')
            ->assertDontSee('Nave Luego');
        $this->actingAs($seller)->get(route('company.pos.index', ['horizonte' => 'semana']))
            ->assertOk()
            ->assertSee('Nave Luego');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(RouteDeparture $departure, array $extra): array
    {
        return array_merge([
            'departure_id' => $departure->id,
            'first_name' => 'Ana',
            'last_name' => 'Ríos',
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'passenger_type' => 'adult',
            'hand_kg' => 5,
            'hold_kg' => 10,
        ], $extra);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function seller(string $name, string $ruc): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'ruc' => $ruc,
            'email' => strtolower(str_replace(' ', '', $name)).'@test.pe',
            'status' => 'active',
        ]);
        $seller = User::factory()->create();
        $seller->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $organization->id]);
        $seller->organizations()->attach($organization->id, ['status' => 'active']);

        return [$seller, $organization];
    }

    private function departure(Organization $organization, string $vesselName): RouteDeparture
    {
        $origin = Port::create(['name' => 'Puerto '.$vesselName, 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Destino '.$vesselName, 'city' => 'Pebas', 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::create([
            'organization_id' => $organization->id,
            'name' => $vesselName,
            'registration_number' => 'MAT-'.$vesselName,
            'vessel_type' => 'rapido',
            'seat_capacity' => 20,
            'status' => 'ready',
        ]);
        $master = MasterRoute::create([
            'code' => 'POS-'.$vesselName,
            'modality' => 'fluvial',
            'origin_city' => 'Iquitos',
            'destination_city' => 'Pebas',
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'status' => 'active',
        ]);
        $route = TransportRoute::create([
            'organization_id' => $organization->id,
            'master_route_id' => $master->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
        ]);

        return RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addHours(5),
            'fare' => 95,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
    }

    private function seat(RouteDeparture $departure, string $code, int $column = 1): VesselSeat
    {
        return $departure->vessel->seats()->create([
            'code' => $code,
            'row_position' => 1,
            'column_position' => $column,
            'deck' => 'principal',
            'space_type' => 'seat',
            'is_available' => true,
        ]);
    }
}
