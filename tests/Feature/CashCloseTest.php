<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CargoShipment;
use App\Models\CashSession;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Services\CashDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 15:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_shift_balances_cash_and_lists_the_yape_operation(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Cuadre', '20641000001');
        $departure = $this->departure($organization);
        $seat = $this->seat($departure, '2A');
        $this->open($seller, $organization, 20);

        $this->actingAs($seller)->post(route('company.pos.store'), $this->ticket($departure, $seat, [
            'method' => 'cash',
            'cash_tendered' => 100,
        ]))->assertRedirect();
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'method' => 'yape',
            'operation_number' => 'YAPE-7788',
            'amount' => 28,
        ]))->assertRedirect();
        Payment::query()->where('method', 'yape')->update(['commission_amount' => 4]);

        $this->actingAs($seller)->get(route('company.cash-close.index'))
            ->assertOk()
            ->assertSee('S/ 115.00')
            ->assertSee('YAPE-7788')
            ->assertSee(__('cash.commission'))
            ->assertSee('S/ 4.00')
            ->assertDontSee('S/ 143.00');

        $this->actingAs($seller)->post(route('company.cash-close.close', CashSession::query()->firstOrFail()), [
            'counted_cash' => 115,
        ])->assertRedirect();

        $session = CashSession::query()->firstOrFail();
        $this->assertSame('115.00', $session->expected_cash);
        $this->assertSame('115.00', $session->counted_cash);
        $this->assertSame('0.00', $session->difference);
        $this->assertNull($session->closing_note);
        $this->assertStringContainsString('YAPE-7788', $this->get(route('company.cash-close.export'))->streamedContent());
        $this->get(route('company.cash-close.print', $session))->assertOk()->assertSee('S/ 115.00');
    }

    public function test_a_difference_requires_a_note_for_over_and_short(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Diferencia', '20641000002');
        $this->actingAs($seller);

        $this->open($seller, $organization, 50);
        $this->post(route('company.cash-close.close', CashSession::query()->first()), [
            'counted_cash' => 51,
        ])->assertRedirect()->assertSessionHasErrors('closing_note');
        $this->assertNull(CashSession::query()->first()->closed_at);

        $this->post(route('company.cash-close.close', CashSession::query()->first()), [
            'counted_cash' => 51,
            'closing_note' => 'Sobra un sol',
        ])->assertRedirect();
        $this->assertSame('1.00', CashSession::query()->first()->difference);

        $this->open($seller, $organization, 40);
        $short = CashSession::query()->whereNull('closed_at')->firstOrFail();
        $this->post(route('company.cash-close.close', $short), [
            'counted_cash' => 39,
        ])->assertRedirect()->assertSessionHasErrors('closing_note');
        $this->post(route('company.cash-close.close', $short), [
            'counted_cash' => 39,
            'closing_note' => 'Falta un sol',
        ])->assertRedirect();
        $this->assertSame('-1.00', $short->fresh()->difference);
    }

    public function test_cash_collection_without_an_open_shift_is_blocked(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Sin Caja', '20641000003');
        $departure = $this->departure($organization);
        $seat = $this->seat($departure, '3A');

        $this->actingAs($seller)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->ticket($departure, $seat, [
            'method' => 'cash',
            'cash_tendered' => 100,
        ]))->assertRedirect()->assertSessionHasErrors('cash_session');
        $this->actingAs($seller)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->ticket($departure, $seat, [
            'method' => 'yape',
            'operation_number' => 'YAPE-1',
        ]))->assertRedirect()->assertSessionHasErrors('cash_session');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_a_closed_shift_payment_cannot_be_edited_and_the_refund_lands_on_the_new_shift(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Devolución', '20641000004');
        $departure = $this->departure($organization);
        $seat = $this->seat($departure, '4A');
        $this->open($seller, $organization, 10);
        $this->actingAs($seller)->post(route('company.pos.store'), $this->ticket($departure, $seat, [
            'method' => 'cash',
            'cash_tendered' => 100,
        ]))->assertRedirect();
        $payment = Payment::query()->firstOrFail();
        $this->actingAs($seller)->post(route('company.cash-close.close', $payment->cash_session_id), [
            'counted_cash' => 105,
            'closing_note' => 'Cierre del mediodía',
        ])->assertRedirect();

        try {
            $payment->update(['amount' => 1]);
            $this->fail('El cobro de un turno cerrado no se edita.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertSame('95.00', $payment->fresh()->amount);
        $this->assertSame('confirmed', $payment->fresh()->status);

        $this->open($seller, $organization, 0);
        $reservation = Reservation::query()->firstOrFail();
        $this->actingAs($seller)->post(route('company.pos.void', $reservation), [
            'reason' => 'El pasajero no viaja',
        ])->assertRedirect();

        $this->assertSame('confirmed', $payment->fresh()->status);
        $this->assertSame('95.00', $payment->fresh()->amount);
        $refund = Payment::query()->where('amount', '<', 0)->firstOrFail();
        $this->assertSame((int) CashSession::query()->whereNull('closed_at')->value('id'), $refund->cash_session_id);
        $this->assertSame('-95.00', $refund->cash_tendered);
        $this->assertSame('El pasajero no viaja', $refund->adjustment_note);
    }

    public function test_recipient_pay_stays_out_of_the_shift_until_delivery(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Destino', '20641000005');
        $departure = $this->departure($organization);
        $this->open($seller, $organization, 15);
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'method' => 'recipient',
            'route_departure_id' => $departure->id,
            'amount' => 30,
        ]))->assertRedirect();
        $shipment = CargoShipment::query()->firstOrFail();

        $this->actingAs($seller)->get(route('company.cash-close.index'))
            ->assertOk()
            ->assertSee($shipment->code)
            ->assertSee(__('cash.age_today'))
            ->assertSee('S/ 15.00')
            ->assertDontSee('S/ 45.00');

        $this->actingAs($seller)->patch(route('company.parcels.board', $shipment))->assertRedirect();
        $this->actingAs($seller)->patch(route('company.parcels.deliver', $shipment), [
            'collector_document' => '87654321',
            'method' => 'cash',
            'cash_tendered' => 30,
        ])->assertRedirect();

        $this->actingAs($seller)->get(route('company.cash-close.index'))
            ->assertOk()
            ->assertDontSee($shipment->code)
            ->assertSee('S/ 45.00');
        $this->assertSame(CashSession::query()->value('id'), Payment::query()->value('cash_session_id'));
    }

    public function test_a_shift_that_crosses_midnight_is_reported_on_the_opening_day(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Noche', '20641000006', 'company_admin');
        Carbon::setTestNow(Carbon::parse('2026-11-17 04:00:00'));
        $this->open($seller, $organization, 8);
        Carbon::setTestNow(Carbon::parse('2026-11-17 06:00:00'));
        $this->actingAs($seller)->post(route('company.cash-close.close', CashSession::query()->first()), [
            'counted_cash' => 8,
            'closing_note' => 'Cruce de medianoche',
        ])->assertRedirect();

        $this->actingAs($seller)->get(route('company.cash-close.index', ['fecha' => '2026-11-16']))
            ->assertOk()
            ->assertSee('Cruce de medianoche');
        $this->actingAs($seller)->get(route('company.cash-close.index', ['fecha' => '2026-11-17']))
            ->assertOk()
            ->assertDontSee('Cruce de medianoche');
    }

    public function test_another_company_and_an_agency_seller_cannot_open_the_cash_desk(): void
    {
        [$seller, $organization] = $this->seller('Vendedor Dueño', '20641000007', 'company_admin');
        $this->open($seller, $organization, 12);
        $this->actingAs($seller)->post(route('company.cash-close.close', CashSession::query()->first()), [
            'counted_cash' => 12,
            'closing_note' => 'Caja de la dueña',
        ])->assertRedirect();
        $session = CashSession::query()->firstOrFail();

        [$other] = $this->seller('Vendedor Ajeno', '20641000008', 'company_admin');
        $this->actingAs($other)->get(route('company.cash-close.index'))->assertOk()->assertDontSee('Caja de la dueña');
        $this->actingAs($other)->get(route('company.cash-close.print', $session))->assertForbidden();

        $agency = Agency::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Agencia Caja',
            'ruc' => '20941000009',
            'contact_name' => 'Luz',
            'phone' => '065000111',
            'status' => 'active',
            'max_surcharge_percent' => 0,
        ]);
        $agent = User::factory()->create(['agency_id' => $agency->id]);
        $agent->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $organization->id]);
        $agent->organizations()->attach($organization->id, ['status' => 'active']);
        $this->actingAs($agent)->get(route('company.cash-close.index'))->assertForbidden();
    }

    public function test_an_old_ticket_uses_the_reservation_seller(): void
    {
        [$admin, $organization] = $this->seller('Admin Caja', '20641000010', 'company_admin');
        $departure = $this->departure($organization);
        $ancient = User::factory()->create(['name' => 'Cajero Antiguo']);
        $reservation = Reservation::query()->create([
            'route_departure_id' => $departure->id,
            'sold_by_user_id' => $ancient->id,
            'code' => 'POS-OLD-1',
            'contact_name' => 'Pasajero Viejo',
            'contact_email' => 'viejo@pos.navegaya.test',
            'contact_phone' => '999',
            'contact_document' => '12345678',
            'total_amount' => 12,
            'base_amount' => 12,
            'status' => 'confirmed',
            'sales_channel' => 'counter',
            'paid_at' => now(),
        ]);
        Payment::query()->create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => 'cash',
            'provider' => 'pos',
            'provider_reference' => 'OLD-CAJA-1',
            'amount' => 12,
            'cash_tendered' => 12,
            'cash_change' => 0,
            'currency_code' => 'PEN',
            'commission_rate' => 0,
            'commission_amount' => 0,
            'operator_net' => 12,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('company.cash-close.index'))
            ->assertOk()
            ->assertSee('Cajero Antiguo');
    }

    public function test_the_company_cannot_change_a_printed_sigla_but_the_platform_admin_can(): void
    {
        [$admin, $organization] = $this->seller('Admin Sigla', '20641000011', 'company_admin');
        $organization->update([
            'short_code' => 'RIORE',
            'address' => 'Malecón',
            'base_city' => 'Iquitos',
            'contact_name' => 'Ana',
        ]);
        $this->open($admin, $organization, 0);
        $this->actingAs($admin)->post(route('company.parcels.store'), $this->parcel())->assertRedirect();
        $this->assertStringStartsWith('ENC-RIORE-', CargoShipment::query()->value('code'));

        $this->actingAs($admin)->patch(route('company.details.update'), [
            'legal_name' => 'Admin Sigla',
            'address' => 'Malecón',
            'base_city' => 'Iquitos',
            'contact_name' => 'Ana',
            'short_code' => 'OTRO',
        ])->assertRedirect()->assertSessionHasErrors('short_code');
        $this->assertSame('RIORE', $organization->fresh()->short_code);

        $platform = User::factory()->create();
        $platform->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador'])->id);
        $this->actingAs($admin)->patch(route('admin.companies.short-code.update', $organization), [
            'short_code' => 'NUEVA',
        ])->assertForbidden();
        $this->actingAs($platform)->patch(route('admin.companies.short-code.update', $organization), [
            'short_code' => 'NUEVA',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('NUEVA', $organization->fresh()->short_code);
        $this->assertStringContainsString('no van a coincidir', (string) session('success'));
    }

    private function sigla(string $ruc): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $n = (int) substr($ruc, -4);

        return $alphabet[intdiv($n, 676) % 26].$alphabet[intdiv($n, 26) % 26].$alphabet[$n % 26].'X';
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function seller(string $name, string $ruc, string $role = 'company_counter'): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'ruc' => $ruc,
            'email' => strtolower(str_replace(' ', '', $name)).'@test.pe',
            'status' => 'active',
            'short_code' => $this->sigla($ruc),
        ]);
        $seller = User::factory()->create(['name' => $name]);
        $seller->roles()->attach(Role::firstOrCreate(['code' => $role], ['name' => $role])->id, ['organization_id' => $organization->id]);
        $seller->organizations()->attach($organization->id, ['status' => 'active']);

        return [$seller, $organization];
    }

    private function open(User $seller, Organization $organization, float $float): CashSession
    {
        return app(CashDesk::class)->open($seller, $organization->id, null, $float);
    }

    private function departure(Organization $organization): RouteDeparture
    {
        $origin = Port::query()->create(['name' => 'Origen '.$organization->ruc, 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Destino '.$organization->ruc, 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Nave '.$organization->ruc,
            'registration_number' => 'CJ-'.$organization->ruc,
            'vessel_type' => 'lancha',
            'seat_capacity' => 20,
            'status' => 'ready',
        ]);
        $master = MasterRoute::query()->create([
            'code' => 'CJ-'.$organization->ruc,
            'modality' => 'fluvial',
            'origin_city' => 'Iquitos',
            'destination_city' => 'Nauta',
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'status' => 'active',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id,
            'master_route_id' => $master->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
        ]);

        return RouteDeparture::query()->create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addHours(5),
            'fare' => 95,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
    }

    private function seat(RouteDeparture $departure, string $code): VesselSeat
    {
        return $departure->vessel->seats()->create([
            'code' => $code,
            'row_position' => 1,
            'column_position' => 1,
            'deck' => 'principal',
            'space_type' => 'seat',
            'is_available' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ticket(RouteDeparture $departure, VesselSeat $seat, array $extra): array
    {
        return array_merge([
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function parcel(array $extra = []): array
    {
        return array_merge([
            'sender_name' => 'Rosa Quispe',
            'sender_document' => '44556677',
            'sender_phone' => '065600100',
            'recipient_name' => 'Luis Paredes',
            'recipient_document' => '12345678',
            'recipient_phone' => '065600200',
            'description' => 'Caja de fariña',
            'package_count' => 1,
            'declared_weight_kg' => 6,
            'length_cm' => 40,
            'width_cm' => 30,
            'height_cm' => 20,
            'amount' => 28,
            'method' => 'cash',
            'cash_tendered' => 30,
        ], $extra);
    }
}
