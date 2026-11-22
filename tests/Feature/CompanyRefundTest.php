<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TicketRefund;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Models\WebFareRefund;
use App\Support\RefundRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_passenger_regret_returns_the_typed_fare_and_keeps_the_service_fee(): void
    {
        [$admin, $organization] = $this->company('Devolución SAC', '20672000001');
        $departure = $this->departure($organization);
        $payment = $this->sale($departure, 'BOL-ARREP', 'Ana Arrepentida', '80.00', '6.40', '80.00', '86.40');
        $passenger = $this->passenger($payment->reservation, 'ana.arrepentida@viajero.test');
        $seat = $this->hold($departure, $payment->reservation, '2A');

        $this->actingAs($passenger)->get(route('tickets.show', 'BOL-ARREP'))
            ->assertOk()
            ->assertSee(RefundRule::REGRET_RULE)
            ->assertSee('S/ 80.00');
        $this->actingAs($passenger)->post(route('tickets.refund', 'BOL-ARREP'), [
            'reason' => 'Me arrepiento del viaje',
        ])->assertRedirect();

        $refund = TicketRefund::query()->firstOrFail();
        $this->assertSame('requested', $refund->status);
        $this->assertSame('0.00', (string) $refund->fee_amount);

        $this->actingAs($admin)->post(route('company.refunds.approve', $refund), ['amount' => '86.40'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('company.refunds.approve', $refund), ['amount' => '80.01'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, WebFareRefund::query()->count());

        $this->actingAs($admin)->post(route('company.refunds.approve', $refund), ['amount' => '50.00'])
            ->assertRedirect();
        $refund->refresh();
        $this->assertSame('approved', $refund->status);
        $this->assertSame('50.00', (string) $refund->amount);
        $this->assertSame('0.00', (string) $refund->fee_amount);
        $this->assertNull($seat->fresh()->vessel_seat_id);
        $this->assertSame('voided', Ticket::query()->first()->status);
        $this->assertDatabaseHas('web_fare_refunds', ['payment_id' => $payment->id, 'amount' => '50.00']);

        $this->actingAs($passenger)->post(route('tickets.refund', 'BOL-ARREP'), [
            'reason' => 'Quiero otra devolución',
        ])->assertSessionHasErrors('code');

        $this->actingAs($admin)->get(route('company.refunds.index'))
            ->assertOk()
            ->assertSee('Reembolsos')
            ->assertSee('BOL-ARREP')
            ->assertSee(RefundRule::REGRET_RULE)
            ->assertSee('Aprobada, por pagar por NavegaYA')
            ->assertSee('data-count="approved">S/ 50.00<', false)
            ->assertDontSee('refund.approved');
        $this->actingAs($admin)->get(route('company.collections.index'))
            ->assertSee('data-count="refunds">S/ 50.00<', false)
            ->assertSee('data-count="pending">S/ 30.00<', false)
            ->assertSee('data-count="net">S/ 80.00<', false);
    }

    public function test_a_policy_percent_calculates_the_regret_and_a_cancellation_returns_the_fee(): void
    {
        [$admin, $organization] = $this->company('Política SAC', '20672000002');
        $organization->update(['refund_regret_percent' => 50]);
        $departure = $this->departure($organization);
        $regret = $this->sale($departure, 'BOL-POL', 'Bea Política', '80.00', '6.40', '80.00', '86.40');
        $passenger = $this->passenger($regret->reservation, 'bea.politica@viajero.test');

        $this->actingAs($passenger)->post(route('tickets.refund', 'BOL-POL'), [
            'reason' => 'Me arrepiento del viaje',
        ])->assertRedirect();
        $first = TicketRefund::query()->where('reservation_id', $regret->reservation_id)->firstOrFail();
        $this->assertSame('40.00', (string) $first->amount);
        $this->assertSame('0.00', (string) $first->fee_amount);
        $this->actingAs($admin)->post(route('company.refunds.approve', $first))->assertRedirect();
        $this->assertDatabaseHas('web_fare_refunds', ['payment_id' => $regret->id, 'amount' => '40.00']);

        $cancelled = $this->sale($departure, 'BOL-CAN', 'Celia Cancelada', '80.00', '6.40', '80.00', '86.40');
        $this->hold($departure, $cancelled->reservation, '3A');
        $this->actingAs($admin)->patch(route('company.departures.status', $departure), ['status' => 'cancelled'])
            ->assertRedirect();
        $second = TicketRefund::query()->where('reservation_id', $cancelled->reservation_id)->firstOrFail();
        $this->assertSame('86.40', (string) $second->amount);
        $this->assertSame('80.00', (string) $second->fare_amount);
        $this->assertSame('6.40', (string) $second->fee_amount);
        $this->assertStringContainsString(RefundRule::CANCEL_RULE, $second->explanation);

        $this->actingAs($admin)->post(route('company.refunds.approve', $second))->assertRedirect();
        $second->refresh();
        $this->assertSame('approved', $second->status);
        $this->assertSame('86.40', (string) $second->amount);
        $this->assertDatabaseHas('web_fare_refunds', ['payment_id' => $cancelled->id, 'amount' => '80.00']);
        $this->assertSame('voided', $cancelled->reservation->seats()->first()->ticket->status);
        $this->actingAs($admin)->get(route('company.collections.index'))
            ->assertSee('data-count="refunds">S/ 120.00<', false)
            ->assertSee('data-count="pending">S/ 40.00<', false);
    }

    public function test_cash_refund_needs_an_open_drawer_with_enough_money_and_a_boarded_ticket_cannot_be_refunded(): void
    {
        [$admin, $organization] = $this->company('Muelle SAC', '20672000003');
        $departure = $this->departure($organization);
        $cash = $this->sale($departure, 'BOL-CAJA', 'Dina Muelle', '80.00', '0.00', '80.00', '80.00', 'counter', 'cash');
        $this->actingAs($admin)->post(route('company.refunds.store'), [
            'code' => 'BOL-CAJA', 'amount' => '95.00', 'reason' => 'El pasajero se arrepintió',
        ])->assertSessionHasErrors('amount');
        $this->actingAs($admin)->post(route('company.refunds.store'), [
            'code' => 'BOL-CAJA', 'amount' => '80.00', 'reason' => 'El pasajero se arrepintió',
        ])->assertRedirect();
        $refund = TicketRefund::query()->firstOrFail();
        $this->actingAs($admin)->post(route('company.refunds.approve', $refund))->assertRedirect();
        $this->actingAs($admin)->post(route('company.refunds.pay', $refund))->assertSessionHasErrors('cash');

        $this->actingAs($admin)->post(route('company.cash-close.open'), ['opening_float' => 10])->assertRedirect();
        $this->actingAs($admin)->post(route('company.refunds.pay', $refund))->assertSessionHasErrors('cash');
        $short = CashSession::query()->where('user_id', $admin->id)->whereNull('closed_at')->firstOrFail();
        $this->actingAs($admin)->post(route('company.cash-close.close', $short), ['counted_cash' => 10])->assertRedirect();

        $this->actingAs($admin)->post(route('company.cash-close.open'), ['opening_float' => 100])->assertRedirect();
        $this->actingAs($admin)->post(route('company.refunds.pay', $refund))->assertRedirect();
        $open = CashSession::query()->where('user_id', $admin->id)->whereNull('closed_at')->firstOrFail();
        $refund->refresh();
        $this->assertSame('paid', $refund->status);
        $this->assertDatabaseHas('payments', [
            'cash_session_id' => $open->id,
            'reservation_id' => $cash->reservation_id,
            'amount' => '-80.00',
        ]);

        $card = $this->sale($departure, 'BOL-YAPE', 'Eva Yape', '80.00', '0.00', '80.00', '80.00', 'counter', 'yape');
        $this->actingAs($admin)->post(route('company.refunds.store'), [
            'code' => 'BOL-YAPE', 'amount' => '80.00', 'reason' => 'Se devuelve el yape',
        ])->assertRedirect();
        $yape = TicketRefund::query()->where('reservation_id', $card->reservation_id)->firstOrFail();
        $this->actingAs($admin)->post(route('company.refunds.approve', $yape))->assertRedirect();
        $this->actingAs($admin)->post(route('company.refunds.pay', $yape))->assertSessionHasErrors('operation_number');
        $this->actingAs($admin)->post(route('company.refunds.pay', $yape), ['operation_number' => 'OP-YAPE'])->assertRedirect();
        $this->assertSame('paid', $yape->fresh()->status);
        $this->assertSame('OP-YAPE', $yape->fresh()->operation_number);

        $boarded = $this->sale($departure, 'BOL-ABORDO', 'Félix Abordo', '80.00', '6.40', '80.00', '86.40');
        $held = $this->hold($departure, $boarded->reservation, '4A', 'boarded');
        $passenger = $this->passenger($boarded->reservation, 'felix.abordo@viajero.test');
        $this->actingAs($passenger)->post(route('tickets.refund', 'BOL-ABORDO'), [
            'reason' => 'Quiero devolverlo',
        ])->assertSessionHasErrors('code');
        $this->assertNotNull($held->fresh()->vessel_seat_id);
        $this->assertSame(0, TicketRefund::query()->where('reservation_id', $boarded->reservation_id)->count());
    }

    public function test_another_company_and_the_seller_cannot_touch_a_refund(): void
    {
        [$admin, $organization] = $this->company('Visible SAC', '20672000004');
        [$other] = $this->company('Ajena SAC', '20672000005');
        $departure = $this->departure($organization);
        $payment = $this->sale($departure, 'BOL-MIA', 'Gina Visible', '80.00', '6.40', '80.00', '86.40');
        $passenger = $this->passenger($payment->reservation, 'gina.visible@viajero.test');
        $this->actingAs($passenger)->post(route('tickets.refund', 'BOL-MIA'), [
            'reason' => 'Me arrepiento del viaje',
        ])->assertRedirect();
        $refund = TicketRefund::query()->firstOrFail();
        $seller = $this->member($organization, 'company_counter');
        $operator = $this->member($organization, 'company_operator');
        $finance = $this->member($organization, 'company_finance');
        $super = User::factory()->create();
        $super->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super'])->id);

        $this->actingAs($other)->get(route('company.refunds.index'))->assertOk()->assertDontSee('BOL-MIA');
        $this->actingAs($other)->post(route('company.refunds.approve', $refund), ['amount' => '10.00'])->assertNotFound();
        $this->actingAs($seller)->get(route('company.refunds.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.refunds.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('company.refunds.index'))->assertOk()->assertSee('Reembolsos')->assertSee('BOL-MIA');
        $this->actingAs($super)->get(route('admin.refunds.index'))
            ->assertOk()
            ->assertSee('Reembolsos e incidencias de pago')
            ->assertSee('Pendientes')
            ->assertSee('BOL-MIA')
            ->assertSee(RefundRule::REGRET_RULE);

        $departure->update(['departure_at' => now()->subHour()]);
        $held = $this->hold($departure, $payment->reservation, '5A');
        $this->actingAs($admin)->post(route('company.refunds.approve', $refund), ['amount' => '20.00'])->assertRedirect();
        $this->assertNotNull($held->fresh()->vessel_seat_id);
        $this->assertSame('issued', $held->ticket->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $name, string $ruc): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => $name, 'commercial_name' => $name,
            'ruc' => $ruc, 'email' => strtolower($ruc).'@test.pe', 'status' => 'active', 'modality' => 'fluvial', 'commission_rate' => 8,
        ]);
        $admin = User::factory()->create(['name' => 'Ada '.$name]);
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function member(Organization $organization, string $code): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => $code], ['name' => $code])->id, ['organization_id' => $organization->id]);
        $user->organizations()->attach($organization->id, ['status' => 'active']);

        return $user;
    }

    private function departure(Organization $organization): RouteDeparture
    {
        $origin = Port::query()->create(['name' => 'Origen '.$organization->id, 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Destino '.$organization->id, 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => 'Nave '.$organization->id, 'registration_number' => 'MAT-'.$organization->id,
            'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'DEV-'.$organization->id,
        ]);

        return RouteDeparture::query()->create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(),
            'fare' => 80, 'status' => 'scheduled', 'is_published' => true,
        ]);
    }

    private function sale(RouteDeparture $departure, string $code, string $passenger, string $base, string $fee, string $net, string $total, string $channel = 'web', string $method = 'card'): Payment
    {
        $reservation = Reservation::query()->create([
            'route_departure_id' => $departure->id,
            'code' => $code,
            'contact_name' => $passenger,
            'contact_email' => strtolower($code).'@viajero.test',
            'contact_phone' => '999000111',
            'base_amount' => $base,
            'discount_amount' => 0,
            'service_fee_amount' => $fee,
            'total_amount' => $total,
            'status' => 'confirmed',
            'sales_channel' => $channel,
            'payment_method' => $method,
            'paid_at' => now(),
        ]);

        return Payment::query()->create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => $method,
            'provider' => $channel === 'web' ? 'sandbox' : 'pos',
            'provider_reference' => 'PAY-'.$code,
            'amount' => $total,
            'commission_rate' => $channel === 'web' ? '8.00' : '0.00',
            'commission_amount' => $fee,
            'operator_net' => $net,
            'commission_status' => 'pending',
            'status' => 'confirmed',
            'paid_at' => now(),
            'provider_payload' => ['commission_model' => 'passenger_fee'],
        ]);
    }

    private function passenger(Reservation $reservation, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $reservation->update(['user_id' => $user->id, 'contact_email' => $email]);

        return $user;
    }

    private function hold(RouteDeparture $departure, Reservation $reservation, string $code, string $status = 'issued'): ReservationSeat
    {
        $vesselSeat = VesselSeat::query()->create([
            'vessel_id' => $departure->vessel_id,
            'code' => $code,
            'deck' => 'principal',
            'row_position' => random_int(1, 20),
            'column_position' => random_int(1, 4),
        ]);
        $seat = ReservationSeat::query()->create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $vesselSeat->id,
            'passenger_name' => $reservation->contact_name,
            'document_number' => '12345678',
        ]);
        Ticket::query()->create([
            'reservation_seat_id' => $seat->id,
            'code' => 'TKT-'.$code.'-'.$reservation->id,
            'boarding_token' => 'TOK-'.$code.'-'.$reservation->id,
            'status' => $status,
            'issued_at' => now(),
            'boarded_at' => $status === 'boarded' ? now() : null,
        ]);

        return $seat;
    }
}
