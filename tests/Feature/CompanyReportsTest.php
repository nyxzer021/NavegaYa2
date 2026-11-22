<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyLot;
use App\Models\CargoShipment;
use App\Models\CashSession;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\RouteDepartureSeatHold;
use App\Models\TicketRefund;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Services\AgencyAccounts;
use App\Services\CompanyCollections;
use App\Services\CompanyReports;
use App\Services\PaymentReceipts;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_match_the_source_screens_and_exclude_the_web_fee(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22 15:00:00', 'America/Lima'));
        [$admin, $organization] = $this->company('Río Reportes SAC', '20671000071');
        [$other] = $this->company('Ajena Reportes SAC', '20671000072');
        $seller = $this->member($organization, 'company_counter', '=Nila');
        $finance = $this->member($organization, 'company_finance', 'Fina');
        $operator = $this->member($organization, 'company_operator', 'Omar');
        $departure = $this->departure($organization);
        $seats = collect(['A1', 'A2', 'A3', 'A4'])->map(fn (string $code, int $row): VesselSeat => VesselSeat::query()->create([
            'vessel_id' => $departure->vessel_id, 'code' => $code, 'deck' => 'principal', 'row_position' => $row + 1, 'column_position' => 1,
        ]));
        $web = $this->sale($departure, 'BOL-WEB', 'Ana Web', 'web', '80.00', '6.40', '80.00', '86.40', $seller);
        $dock = $this->sale($departure, 'BOL-MUELLE', 'Luis Muelle', 'counter', '50.00', '0.00', '50.00', '50.00', $seller);
        $agencyUser = $this->member($organization, 'company_counter', 'Iris');
        $agencySale = $this->sale($departure, 'BOL-AGENCIA', 'Iris Agencia', 'agency', '40.00', '0.00', '40.00', '40.00', null);
        $this->seat($web->reservation, $seats[0]);
        $this->seat($dock->reservation, $seats[1]);
        $agency = Agency::query()->create([
            'organization_id' => $organization->id, 'name' => 'Agencia Norte', 'ruc' => '20671000073',
            'contact_name' => 'Luz', 'phone' => '065600173', 'status' => 'active',
        ]);
        $agencySale->reservation->update(['agency_id' => $agency->id]);
        $lot = AgencyLot::query()->create([
            'agency_id' => $agency->id, 'route_departure_id' => $departure->id, 'unit_price' => '40.00', 'payment_status' => 'pending',
        ]);
        RouteDepartureSeatHold::query()->create([
            'route_departure_id' => $departure->id, 'vessel_seat_id' => $seats[2]->id, 'kind' => 'agency', 'agency_id' => $agency->id, 'agency_lot_id' => $lot->id,
        ]);
        CargoShipment::query()->create([
            'organization_id' => $organization->id, 'code' => 'ENC-REP-1', 'sender_name' => 'Mario', 'sender_document' => '11112222',
            'sender_phone' => '999111222', 'recipient_name' => 'Nora', 'recipient_document' => '33334444', 'recipient_phone' => '999333444',
            'description' => 'Caja', 'package_count' => 1, 'declared_weight_kg' => '4.50', 'amount' => '25.00',
            'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'received', 'received_at' => now(),
        ]);
        $parcel = CargoShipment::query()->where('code', 'ENC-REP-1')->firstOrFail();
        Payment::query()->create([
            'cargo_shipment_id' => $parcel->id, 'purpose' => 'cargo', 'method' => 'cash', 'provider' => 'sandbox',
            'provider_reference' => 'PAY-ENC-REP-1', 'amount' => '25.00', 'commission_amount' => '0.00', 'operator_net' => '25.00',
            'status' => 'confirmed', 'paid_at' => now(),
        ]);
        CargoShipment::query()->create([
            'organization_id' => $organization->id, 'code' => 'ENC-REP-2', 'sender_name' => 'Olga', 'sender_document' => '55556666',
            'sender_phone' => '999555666', 'recipient_name' => 'Paco', 'recipient_document' => '77778888', 'recipient_phone' => '999777888',
            'description' => 'Sobre', 'package_count' => 1, 'declared_weight_kg' => '1.50', 'amount' => '10.00',
            'payment_method' => 'recipient', 'payment_status' => 'pending', 'status' => 'received', 'received_at' => now(),
        ]);
        CashSession::query()->create([
            'organization_id' => $organization->id, 'user_id' => $seller->id, 'opened_at' => now(), 'opening_float' => '20.00',
            'closed_at' => now(), 'counted_cash' => '20.00', 'expected_cash' => '20.00', 'difference' => '-5.00', 'closed_by_user_id' => $admin->id,
        ]);
        CashSession::query()->create([
            'organization_id' => $organization->id, 'user_id' => $agencyUser->id, 'opened_at' => now(), 'opening_float' => '10.00',
            'closed_at' => now(), 'counted_cash' => '12.00', 'expected_cash' => '10.00', 'difference' => '2.00', 'closed_by_user_id' => $admin->id,
        ]);
        $this->refund($organization, $web, 'requested', '20.00');
        $this->refund($organization, $dock, 'paid', '15.00');

        $report = app(CompanyReports::class)->report($organization, 'hoy', '', '');
        $catalog = app(PaymentReceipts::class)->catalog($organization, ['desde' => $report['range']['from'], 'hasta' => $report['range']['to']]);
        $accounts = app(AgencyAccounts::class)->statement($organization);
        $week = app(CompanyCollections::class)->period('semana', '2026-11-22');
        $weekReport = app(CompanyReports::class)->report($organization, 'semana', '', '');

        $this->assertSame(8000, $report['channels'][0]['cents']);
        $this->assertSame(5000, $report['channels'][1]['cents']);
        $this->assertSame(4000, $report['channels'][2]['cents']);
        $this->assertSame(17000, $report['total_cents']);
        $this->assertSame($report['total_cents'], array_sum(array_column($report['channels'], 'cents')));
        $this->assertSame($report['total_cents'], array_sum(array_column($report['routes'], 'cents')));
        $this->assertSame($report['collections']['net_cents'], $report['channels'][0]['cents']);
        $this->assertSame(640, $report['collections']['fee_cents']);
        $this->assertSame($catalog['kpis']['ticket_cents'], $report['total_cents']);
        $this->assertSame($catalog['kpis']['parcel_cents'], $report['parcels']['cents']);
        $this->assertSame(2500, $report['parcels']['cents']);
        $this->assertSame('6.00', $report['parcels']['kg']);
        $this->assertSame(1000, $report['parcels']['due_cents']);
        $this->assertSame(2, $report['occupancy'][0]['sold']);
        $this->assertSame(4, $report['occupancy'][0]['capacity']);
        $this->assertSame(50, $report['occupancy'][0]['percent']);
        $this->assertSame(-500, $report['cash']['rows'][0]['cents']);
        $this->assertSame(-300, $report['cash']['cents']);
        $this->assertSame(1, $report['refunds']['requested']);
        $this->assertSame(1500, $report['refunds']['paid_cents']);
        $this->assertSame($accounts['totals'], $report['agency_accounts']);
        $this->assertSame(4000, $report['agency_accounts']['balance_cents']);
        $this->assertSame($week['from'], $weekReport['range']['from']);
        $this->assertSame($week['to'], $weekReport['range']['to']);
        $this->assertSame(
            app(CompanyCollections::class)->statement($organization, $week)['net_cents'],
            $weekReport['collections']['net_cents'],
        );

        $page = $this->actingAs($admin)->get(route('company.reports.index', ['vista' => 'hoy']));
        $page->assertOk()
            ->assertSee('Reportes')
            ->assertSee('data-count="web">S/ 80.00', false)
            ->assertSee('data-count="counter">S/ 50.00', false)
            ->assertSee('data-count="agency">S/ 40.00', false)
            ->assertSee('data-count="total">S/ 170.00', false)
            ->assertSee('data-count="collections-net">S/ 80.00', false)
            ->assertSee('data-count="fee">S/ 6.40', false)
            ->assertSee('data-sold="2"', false)
            ->assertSee('data-capacity="4"', false)
            ->assertSee('2 / 4')
            ->assertSee('data-count="parcels">S/ 25.00', false)
            ->assertSee('data-count="due">S/ 10.00', false)
            ->assertSee('data-count="refunds-requested">1', false)
            ->assertSee('data-count="refunds-paid">S/ 15.00', false)
            ->assertSee('data-count="agency-balance">S/ 40.00', false)
            ->assertSee('Faltante')
            ->assertDontSee('S/ 86.40')
            ->assertDontSee('2 / 99');

        $sales = $this->actingAs($admin)->get(route('company.reports.sales', ['vista' => 'hoy']));
        $sales->assertOk();
        $body = $sales->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString("'=Río Norte", $body);
        $this->assertStringContainsString('S/ 170.00', $body);
        $occupancy = $this->actingAs($admin)->get(route('company.reports.occupancy', ['vista' => 'hoy']))->streamedContent();
        $this->assertStringContainsString('2026-11-22 08:00', $occupancy);
        $cash = $this->actingAs($admin)->get(route('company.reports.cash', ['vista' => 'hoy']))->streamedContent();
        $this->assertStringContainsString("'=Nila", $cash);

        $this->actingAs($other)->get(route('company.reports.index', ['vista' => 'hoy']))
            ->assertOk()
            ->assertDontSee('=Río Norte')
            ->assertSee('data-count="total">S/ 0.00', false);
        $this->actingAs($finance)->get(route('company.reports.index', ['vista' => 'hoy']))->assertOk()->assertSee('=Río Norte');
        $this->actingAs($operator)->get(route('company.reports.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.reports.sales'))->assertForbidden();
        $this->actingAs($seller)->get(route('company.reports.index'))->assertForbidden();
    }

    public function test_today_follows_the_lima_day_not_the_utc_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-22 04:30:00'));
        [$admin, $organization] = $this->company('Lima Reportes SAC', '20671000074');
        $departure = $this->departure($organization);
        $this->sale($departure, 'BOL-NOCHE', 'Dentro del día', 'web', '80.00', '6.40', '80.00', '86.40', null);
        $this->sale($departure, 'BOL-MADRUGADA', 'Día siguiente', 'web', '10.00', '0.80', '10.00', '10.80', null, Carbon::parse('2026-11-22 06:00:00'));

        $report = app(CompanyReports::class)->report($organization, 'hoy', '', '');

        $this->assertSame('2026-11-21', $report['range']['from']);
        $this->assertSame(8000, $report['channels'][0]['cents']);
        $this->assertSame(8000, $report['collections']['net_cents']);
        $this->actingAs($admin)->get(route('company.reports.index', ['vista' => 'hoy']))
            ->assertOk()
            ->assertDontSee('Día siguiente');
    }

    private function refund(Organization $organization, Payment $payment, string $status, string $amount): void
    {
        TicketRefund::query()->create([
            'organization_id' => $organization->id,
            'reservation_id' => $payment->reservation_id,
            'payment_id' => $payment->id,
            'source' => 'counter',
            'cause' => 'regret',
            'status' => $status,
            'fare_amount' => $amount,
            'fee_amount' => '0.00',
            'amount' => $amount,
            'explanation' => 'El pasajero desistió',
        ]);
    }

    private function seat(Reservation $reservation, VesselSeat $seat): void
    {
        ReservationSeat::query()->create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $reservation->route_departure_id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => $reservation->contact_name,
            'document_number' => '12345678',
        ]);
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

    private function member(Organization $organization, string $code, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['code' => $code], ['name' => $code])->id, ['organization_id' => $organization->id]);
        $user->organizations()->attach($organization->id, ['status' => 'active']);

        return $user;
    }

    private function departure(Organization $organization): RouteDeparture
    {
        $origin = Port::query()->create(['name' => '=Río Norte', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => 'Nave '.$organization->id, 'registration_number' => 'MAT-'.$organization->id,
            'vessel_type' => 'rapido', 'seat_capacity' => 99, 'status' => 'ready',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'REP-'.$organization->id,
        ]);

        return RouteDeparture::query()->create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id,
            'departure_at' => Carbon::parse('2026-11-22 08:00:00', 'America/Lima'),
            'fare' => 80, 'status' => 'scheduled', 'is_published' => true,
        ]);
    }

    private function sale(RouteDeparture $departure, string $code, string $passenger, string $channel, string $base, string $fee, string $net, string $total, ?User $seller, ?Carbon $paidAt = null): Payment
    {
        $paidAt ??= now();
        $reservation = Reservation::query()->create([
            'route_departure_id' => $departure->id,
            'sold_by_user_id' => $seller?->id,
            'code' => $code,
            'contact_name' => $passenger,
            'contact_email' => strtolower($code).'@viajero.test',
            'contact_phone' => '999000111',
            'base_amount' => $base,
            'discount_amount' => '0.00',
            'service_fee_amount' => $fee,
            'total_amount' => $total,
            'status' => 'confirmed',
            'sales_channel' => $channel,
            'paid_at' => $paidAt,
        ]);

        return Payment::query()->create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => $channel === 'web' ? 'card' : 'cash',
            'provider' => 'sandbox',
            'provider_reference' => 'PAY-'.$code,
            'amount' => $total,
            'commission_rate' => $channel === 'web' ? '8.00' : '0.00',
            'commission_amount' => $fee,
            'operator_net' => $net,
            'commission_status' => 'pending',
            'status' => 'confirmed',
            'paid_at' => $paidAt,
            'provider_payload' => ['commission_model' => 'passenger_fee'],
        ]);
    }
}
