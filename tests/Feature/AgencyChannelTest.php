<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyLot;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Services\CashDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AgencyChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(17, 0));
    }

    public function test_an_agency_sells_only_its_lot_and_keeps_the_navega_ya_fee_at_zero(): void
    {
        [$admin, $organization] = $this->company('Agencia Dueña', '20610000001');
        $organization->update(['commission_rate' => 8, 'physical_sales_commission_rate' => 5]);
        $departure = $this->departure($organization, 'Nave Lote');
        $lotSeat = $this->seat($departure, '1A');
        $other = $this->seat($departure, '1B', 2);
        $agency = $this->agency($organization);
        $seller = $this->seller($organization, $agency);
        $this->lot($admin, $agency, $departure, [$lotSeat->id]);

        $this->actingAs($seller)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $other->id,
            'sale_price' => 95,
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertRedirect()->assertSessionHasErrors('seat_id');
        $this->assertDatabaseCount('reservations', 0);

        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $lotSeat->id,
            'sale_price' => 95,
            'method' => 'cash',
            'cash_tendered' => 100,
        ]))->assertRedirect();

        $reservation = Reservation::firstOrFail();
        $payment = Payment::firstOrFail();
        $this->assertSame('agency', $reservation->sales_channel);
        $this->assertSame($agency->id, $reservation->agency_id);
        $this->assertSame('95.00', $reservation->total_amount);
        $this->assertSame('0.00', $reservation->service_fee_amount);
        $this->assertSame('0.00', $payment->commission_rate);
        $this->assertSame('0.00', $payment->commission_amount);
        $this->assertSame('100.00', $payment->cash_tendered);
        $this->assertSame('5.00', $payment->cash_change);
        $this->assertDatabaseMissing('route_departure_seat_holds', ['vessel_seat_id' => $lotSeat->id]);
    }

    public function test_a_suspended_agency_cannot_sell(): void
    {
        [$admin, $organization] = $this->company('Agencia Pausa', '20610000002');
        $departure = $this->departure($organization, 'Nave Pausa');
        $seat = $this->seat($departure, '2A');
        $agency = $this->agency($organization, 'suspended');
        $seller = $this->seller($organization, $agency);
        $this->lot($admin, $agency, $departure, [$seat->id]);

        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id,
            'sale_price' => 95,
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertUnprocessable();
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_the_sale_price_must_stay_between_the_public_fare_and_the_maximum(): void
    {
        [$admin, $organization] = $this->company('Agencia Tope', '20610000003');
        $departure = $this->departure($organization, 'Nave Tope');
        $seat = $this->seat($departure, '3A');
        $agency = $this->agency($organization);
        $agency->update(['max_surcharge_percent' => 10]);
        $seller = $this->seller($organization, $agency);
        $this->lot($admin, $agency, $departure, [$seat->id]);

        $this->actingAs($seller)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id, 'sale_price' => 94.99, 'method' => 'cash', 'cash_tendered' => 100,
        ]))->assertRedirect()->assertSessionHasErrors('sale_price');

        $this->actingAs($seller)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id, 'sale_price' => 104.51, 'method' => 'cash', 'cash_tendered' => 110,
        ]))->assertRedirect()->assertSessionHasErrors('sale_price');
        $this->assertDatabaseCount('reservations', 0);

        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id, 'sale_price' => 104.50, 'method' => 'yape', 'operation_number' => 'YP-19',
        ]))->assertRedirect();
        $this->assertSame('104.50', Reservation::firstOrFail()->total_amount);
    }

    public function test_a_lot_seat_cannot_be_sold_at_the_dock_or_on_the_web_until_it_is_released(): void
    {
        [$admin, $organization] = $this->company('Agencia Cupo', '20610000004');
        $departure = $this->departure($organization, 'Nave Cupo');
        $seat = $this->seat($departure, '4A');
        $agency = $this->agency($organization);
        $lot = $this->lot($admin, $agency, $departure, [$seat->id]);
        $counter = User::factory()->create();
        $counter->roles()->attach(Role::where('code', 'company_counter')->firstOrFail()->id, ['organization_id' => $organization->id]);
        $counter->organizations()->attach($organization->id, ['status' => 'active']);
        app(CashDesk::class)->open($counter, $organization->id, null, 0);

        $this->actingAs($counter)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id, 'method' => 'cash', 'cash_tendered' => 100,
        ]))->assertRedirect()->assertSessionHasErrors('seat_id');

        $this->post(route('bookings.store', $departure), [
            'contact_name' => 'Web Cupo',
            'contact_email' => 'web-cupo@test.pe',
            'contact_phone' => '999000111',
            'seats' => [$seat->id],
            'passengers' => [['name' => 'Web Cupo', 'document' => '87654321']],
        ])->assertRedirect()->assertSessionHasErrors('seats');
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseHas('route_departure_seat_holds', ['vessel_seat_id' => $seat->id, 'agency_id' => $agency->id]);

        $this->actingAs($admin)->post(route('company.agencies.lots.release', $lot), [
            'seats' => [$seat->id],
            'note' => 'La agencia no las usó',
        ])->assertRedirect();
        $this->assertDatabaseMissing('route_departure_seat_holds', ['vessel_seat_id' => $seat->id]);

        $this->actingAs($counter)->post(route('company.pos.store'), $this->payload($departure, [
            'seat_id' => $seat->id, 'method' => 'cash', 'cash_tendered' => 100,
        ]))->assertRedirect();
        $this->assertSame('counter', Reservation::firstOrFail()->sales_channel);
    }

    public function test_an_agency_cannot_see_or_sell_another_companys_departure(): void
    {
        [$admin, $organization] = $this->company('Agencia A', '20610000005');
        $departure = $this->departure($organization, 'Nave A');
        $seat = $this->seat($departure, '5A');
        $agency = $this->agency($organization, 'active', 'Río Ajeno');
        $seller = $this->seller($organization, $agency);
        $this->lot($admin, $agency, $departure, [$seat->id]);
        [$otherAdmin] = $this->company('Agencia B', '20610000006');

        $this->actingAs($otherAdmin)->get(route('company.agencies.index'))
            ->assertOk()
            ->assertDontSee('Río Ajeno');
        $this->actingAs($otherAdmin)->patch(route('company.agencies.update', $agency), [
            'status' => 'suspended',
            'max_surcharge_percent' => 0,
            'suspension_reason' => 'No es suya',
        ])->assertForbidden();

        $foreign = $this->departure($otherAdmin->organizations()->first(), 'Nave B');
        $foreignSeat = $this->seat($foreign, '9A');
        $this->actingAs($seller)->post(route('company.pos.store'), $this->payload($foreign, [
            'seat_id' => $foreignSeat->id,
            'sale_price' => 95,
            'method' => 'cash',
            'cash_tendered' => 95,
        ]))->assertForbidden();
    }

    public function test_the_invited_seller_only_opens_the_agency_desk(): void
    {
        Notification::fake();
        [$admin, $organization] = $this->company('Agencia Invitada', '20610000007');
        $agency = $this->agency($organization);
        Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor']);

        $this->actingAs($admin)->post(route('company.agencies.invite', $agency), [
            'email' => 'vendedor.agencia@test.pe',
        ])->assertRedirect();
        $token = AgencyInvitation::firstOrFail()->token;

        $this->post(route('agency.invitation.store', $token), [
            'name' => 'Vendedora Lote',
            'password' => 'secreta-larga',
            'password_confirmation' => 'secreta-larga',
        ])->assertRedirect(route('company.pos.index'));

        $seller = User::query()->where('email', 'vendedor.agencia@test.pe')->firstOrFail();
        $this->assertSame($agency->id, $seller->agency_id);
        $this->actingAs($seller)->get(route('company.pos.index'))->assertOk()->assertSee('Punto de venta')->assertSee('Mis lotes')->assertSee('Mis ventas')->assertDontSee('Encomiendas')->assertDontSee('Cierre de caja');
        $this->actingAs($seller)->get(route('company.parcels.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('company.agencies.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('company.agency-lots.index'))->assertOk()->assertSee('Mis lotes');
    }

    public function test_a_hammock_lot_holds_the_requested_quantity_and_never_more_than_the_free_ones(): void
    {
        [$admin, $organization] = $this->company('Agencia Hamacas', '20610000007');
        $departure = $this->departure($organization, 'Nave Hamacas');
        foreach (['H1', 'H2', 'H3'] as $index => $code) {
            $departure->vessel->seats()->create([
                'code' => $code, 'row_position' => 2, 'column_position' => $index + 1,
                'deck' => 'principal', 'space_type' => 'hammock', 'is_available' => true,
            ]);
        }
        $agency = $this->agency($organization);

        $this->actingAs($admin)->post(route('company.agencies.lots.store', $agency), [
            'departure_id' => $departure->id, 'hammocks' => 2, 'unit_price' => 50, 'payment_status' => 'pending',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $held = RouteDepartureSeatHold::query()->where('route_departure_id', $departure->id)->with('seat')->get();
        $this->assertCount(2, $held);
        $this->assertTrue($held->every(fn (RouteDepartureSeatHold $hold): bool => $hold->seat->space_type === 'hammock'));

        $this->actingAs($admin)->post(route('company.agencies.lots.store', $agency), [
            'departure_id' => $departure->id, 'hammocks' => 2, 'unit_price' => 50, 'payment_status' => 'pending',
        ])->assertSessionHasErrors('hammocks');
        $this->assertDatabaseCount('route_departure_seat_holds', 2);
        $this->assertDatabaseCount('agency_lots', 1);
    }

    public function test_only_navega_ya_lifts_a_suspension_it_placed(): void
    {
        [$admin, $organization] = $this->company('Agencia Vigilada', '20610000008');
        $agency = $this->agency($organization);
        $platform = User::factory()->create();
        $platform->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador'])->id);

        $this->actingAs($platform)->post(route('admin.agencies.suspend', $agency), ['suspension_reason' => 'RUC observado'])->assertRedirect();
        $this->assertSame('suspended', $agency->refresh()->status);

        $this->actingAs($admin)->patch(route('company.agencies.update', $agency), [
            'status' => 'active', 'max_surcharge_percent' => 5,
        ])->assertSessionHasErrors('status');
        $this->assertSame('suspended', $agency->refresh()->status);
        $this->assertSame('RUC observado', $agency->suspension_reason);

        $this->actingAs($platform)->post(route('admin.agencies.reactivate', $agency))->assertRedirect();
        $this->assertSame('active', $agency->refresh()->status);
        $this->assertNull($agency->suspended_by_user_id);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $name, string $ruc): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'ruc' => $ruc,
            'email' => strtolower(str_replace(' ', '', $name)).'@test.pe',
            'status' => 'active',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function agency(Organization $organization, string $status = 'active', string $name = 'Río Lotes'): Agency
    {
        return Agency::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'ruc' => '20123456789',
            'contact_name' => 'Luz Paredes',
            'phone' => '065123456',
            'status' => $status,
            'max_surcharge_percent' => 0,
        ]);
    }

    private function seller(Organization $organization, Agency $agency): User
    {
        $seller = User::factory()->create(['agency_id' => $agency->id]);
        $seller->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $organization->id]);
        $seller->organizations()->attach($organization->id, ['status' => 'active']);

        return $seller;
    }

    /**
     * @param  list<int>  $seats
     */
    private function lot(User $admin, Agency $agency, RouteDeparture $departure, array $seats): AgencyLot
    {
        $this->actingAs($admin)->post(route('company.agencies.lots.store', $agency), [
            'departure_id' => $departure->id,
            'seats' => $seats,
            'unit_price' => 70,
            'payment_status' => 'pending',
        ])->assertRedirect();

        return $agency->lots()->firstOrFail();
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
        ], $extra);
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
            'code' => 'AGE-'.$vesselName,
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
