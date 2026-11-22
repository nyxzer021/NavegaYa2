<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyLot;
use App\Models\AgencyLotPayment;
use App\Models\AgencyLotRelease;
use App\Models\CashSession;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAgencyAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payments_follow_the_balance_and_a_void_returns_it(): void
    {
        [$admin, $organization] = $this->company('Cuentas SAC', '20673000001');
        $departure = $this->departure($organization, now()->subDay());
        $agency = Agency::query()->create([
            'organization_id' => $organization->id, 'name' => '=Agencia Río', 'ruc' => '20111000011',
            'contact_name' => 'Luz', 'phone' => '065600111', 'status' => 'active',
        ]);
        $lot = AgencyLot::query()->create([
            'agency_id' => $agency->id, 'route_departure_id' => $departure->id, 'unit_price' => '50.00', 'payment_status' => 'pending',
        ]);
        $this->hold($departure, $lot, '1A', 1);
        $this->hold($departure, $lot, '1B', 2);
        $soldSeat = $this->seat($departure, '1C', 3);
        $reservation = Reservation::query()->create([
            'route_departure_id' => $departure->id, 'agency_id' => $agency->id, 'agency_lot_id' => $lot->id,
            'code' => 'AGE-CUENTA', 'contact_name' => 'Nila Vendida', 'contact_email' => 'nila@viajero.test', 'contact_phone' => '999000111',
            'base_amount' => 50, 'discount_amount' => 0, 'total_amount' => 50,
            'status' => 'confirmed', 'sales_channel' => 'agency', 'paid_at' => now(),
        ]);
        ReservationSeat::query()->create([
            'reservation_id' => $reservation->id, 'route_departure_id' => $departure->id,
            'vessel_seat_id' => $soldSeat->id, 'passenger_name' => 'Nila Vendida', 'document_number' => '12345678',
        ]);
        $released = $this->seat($departure, '1D', 4);
        AgencyLotRelease::query()->create([
            'agency_lot_id' => $lot->id, 'vessel_seat_id' => $released->id, 'note' => 'La agencia no la usó',
        ]);

        $this->actingAs($admin)->get(route('company.agency-commissions.index'))
            ->assertOk()
            ->assertSee('Cuentas de agencias')
            ->assertSee('=Agencia Río')
            ->assertSee('Asignadas 4')
            ->assertSee('Vendidas 1')
            ->assertSee('Liberadas 1')
            ->assertSee('data-count="amount">S/ 150.00<', false)
            ->assertSee('data-count="balance">S/ 150.00<', false)
            ->assertSee('data-count="overdue">S/ 150.00<', false)
            ->assertSee('Saldo vencido')
            ->assertDontSee('lot_payment.recorded');

        $this->actingAs($admin)->post(route('company.agency-commissions.payments.store', $lot), $this->payment('200.00', 'OP-MAS'))
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('agency_lot_payments', 0);

        $this->actingAs($admin)->post(route('company.agency-commissions.payments.store', $lot), $this->payment('60.00', 'OP-60'))
            ->assertRedirect();
        $this->actingAs($admin)->get(route('company.agency-commissions.index'))
            ->assertSee('OP-60')
            ->assertSee('Parcial')
            ->assertSee('data-count="paid">S/ 60.00<', false)
            ->assertSee('data-count="balance">S/ 90.00<', false);

        $this->actingAs($admin)->post(route('company.agency-commissions.payments.store', $lot), $this->payment('90.00', 'OP-90'))
            ->assertRedirect();
        $this->actingAs($admin)->post(route('company.agency-commissions.payments.store', $lot), $this->payment('1.00', 'OP-EXTRA'))
            ->assertSessionHasErrors('amount');
        $this->actingAs($admin)->get(route('company.agency-commissions.index'))
            ->assertSee('Pagado')
            ->assertSee('data-count="paid">S/ 150.00<', false)
            ->assertSee('data-count="balance">S/ 0.00<', false)
            ->assertSee('data-count="overdue">S/ 0.00<', false);
        $this->assertSame('pending', $lot->fresh()->payment_status);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, CashSession::query()->count());

        $second = AgencyLotPayment::query()->where('operation_number', 'OP-90')->firstOrFail();
        $this->actingAs($admin)->post(route('company.agency-commissions.payments.void', $second), [
            'reason' => 'El depósito no llegó',
        ])->assertRedirect();
        $this->assertSame('voided', $second->fresh()->status);
        $this->assertNotNull($second->fresh()->voided_at);
        $this->actingAs($admin)->get(route('company.agency-commissions.index'))
            ->assertSee('El depósito no llegó')
            ->assertSee('Anulado')
            ->assertSee('Parcial')
            ->assertSee('data-count="paid">S/ 60.00<', false)
            ->assertSee('data-count="balance">S/ 90.00<', false)
            ->assertSee('data-count="overdue">S/ 90.00<', false);

        $csv = $this->actingAs($admin)->get(route('company.agency-commissions.export'))->streamedContent();
        $this->assertStringContainsString("'=Agencia Río", $csv);
        $this->assertStringContainsString('150.00', $csv);
        $this->assertStringContainsString('90.00', $csv);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $csv);

        $this->actingAs($admin)->get(route('company.audit.index'))
            ->assertOk()
            ->assertSee('registró un pago de =Agencia Río por S/ 60.00')
            ->assertDontSee('lot_payment.recorded');
    }

    public function test_existing_lots_keep_their_status_and_another_company_cannot_see_them(): void
    {
        [$admin, $organization] = $this->company('Visible SAC', '20673000002');
        [$other] = $this->company('Ajena SAC', '20673000003');
        $departure = $this->departure($organization, now()->addDay());
        $agency = Agency::query()->create([
            'organization_id' => $organization->id, 'name' => 'Agencia Pagada', 'ruc' => '20111000022',
            'contact_name' => 'Nora', 'phone' => '065600222', 'status' => 'active',
        ]);
        $lot = AgencyLot::query()->create([
            'agency_id' => $agency->id, 'route_departure_id' => $departure->id, 'unit_price' => '40.00', 'payment_status' => 'paid',
        ]);
        $this->hold($departure, $lot, '2A', 1);
        $this->hold($departure, $lot, '2B', 2);
        $seller = $this->member($organization, 'company_counter');
        $operator = $this->member($organization, 'company_operator');
        $finance = $this->member($organization, 'company_finance');

        $this->actingAs($admin)->get(route('company.agency-commissions.index'))
            ->assertOk()
            ->assertSee('Agencia Pagada')
            ->assertSee('Pagado')
            ->assertSee('Marcado pagado antes')
            ->assertSee('data-count="amount">S/ 80.00<', false)
            ->assertSee('data-count="paid">S/ 80.00<', false)
            ->assertSee('data-count="balance">S/ 0.00<', false)
            ->assertSee('data-count="overdue">S/ 0.00<', false);
        $this->actingAs($admin)->post(route('company.agency-commissions.payments.store', $lot), $this->payment('10.00', 'OP-NO'))
            ->assertSessionHasErrors('amount');
        $this->assertSame('paid', $lot->fresh()->payment_status);
        $this->assertDatabaseCount('agency_lot_payments', 0);

        $this->actingAs($other)->get(route('company.agency-commissions.index'))->assertOk()->assertDontSee('Agencia Pagada');
        $this->actingAs($other)->post(route('company.agency-commissions.payments.store', $lot), $this->payment('10.00', 'OP-AJENA'))
            ->assertNotFound();
        $this->actingAs($seller)->get(route('company.agency-commissions.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.agency-commissions.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('company.agency-commissions.index'))
            ->assertOk()
            ->assertSee('Cuentas de agencias')
            ->assertSee('Agencia Pagada');
    }

    /**
     * @return array<string, string>
     */
    private function payment(string $amount, string $operation): array
    {
        return [
            'amount' => $amount,
            'paid_on' => now()->timezone('America/Lima')->toDateString(),
            'method' => 'Transferencia',
            'operation_number' => $operation,
            'note' => 'Abono del lote',
        ];
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $name, string $ruc): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => $name, 'commercial_name' => $name,
            'ruc' => $ruc, 'email' => strtolower($ruc).'@test.pe', 'status' => 'active', 'modality' => 'fluvial',
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

    private function departure(Organization $organization, CarbonInterface $when): RouteDeparture
    {
        $origin = Port::query()->create(['name' => 'Origen '.$organization->id, 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Destino '.$organization->id, 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => 'Nave '.$organization->id, 'registration_number' => 'MAT-'.$organization->id,
            'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'AGE-'.$organization->id,
        ]);

        return RouteDeparture::query()->create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => $when,
            'fare' => 50, 'status' => 'scheduled', 'is_published' => true,
        ]);
    }

    private function hold(RouteDeparture $departure, AgencyLot $lot, string $code, int $row): VesselSeat
    {
        $seat = $this->seat($departure, $code, $row);
        RouteDepartureSeatHold::query()->create([
            'route_departure_id' => $departure->id, 'vessel_seat_id' => $seat->id,
            'kind' => 'agency', 'agency_id' => $lot->agency_id, 'agency_lot_id' => $lot->id,
        ]);

        return $seat;
    }

    private function seat(RouteDeparture $departure, string $code, int $row): VesselSeat
    {
        return VesselSeat::query()->create([
            'vessel_id' => $departure->vessel_id, 'code' => $code, 'deck' => 'principal',
            'row_position' => $row, 'column_position' => 1,
        ]);
    }
}
