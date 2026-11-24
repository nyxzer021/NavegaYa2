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
use App\Models\TicketRefund;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Services\CashDesk;
use App\Services\CompanyCollections;
use App\Services\CompanyReports;
use Database\Seeders\LocalReviewDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class StageCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_demo_seeder_confirms_payments_and_refuses_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no puede correr en producción');

        (new LocalReviewDemoSeeder)->run();
    }

    public function test_the_demo_seeder_confirms_two_web_tickets_without_calling_a_provider(): void
    {
        $this->app['env'] = 'local';
        Mail::fake();
        Http::fake();
        $company = Organization::query()->create([
            'type' => 'transport_company',
            'legal_name' => 'Río Revisión SAC',
            'commercial_name' => 'Río Revisión',
            'ruc' => '20680000001',
            'email' => 'revision-visual@navegaya.test',
            'status' => 'active',
            'commission_rate' => 8,
        ]);
        $origin = Port::query()->create(['name' => 'Puerto de Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Puerto de Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        TransportRoute::query()->create([
            'organization_id' => $company->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'code' => 'IQT-NAU',
        ]);

        (new LocalReviewDemoSeeder)->run();

        $this->assertSame(2, Payment::query()->where('provider', 'sandbox')->where('status', 'confirmed')->count());
        foreach (['DEMO-PAGO-01', 'DEMO-PAGO-02'] as $reference) {
            $payment = Payment::query()->where('provider_reference', $reference)->firstOrFail();
            $this->assertSame('86.40', $payment->amount);
            $this->assertSame('6.40', $payment->commission_amount);
            $this->assertSame('80.00', $payment->operator_net);
            $this->assertSame('confirmed', $payment->reservation->status);
            $this->assertSame(1, $payment->reservation->seats()->whereHas('ticket')->count());
        }
        Http::assertNothingSent();
    }

    public function test_a_local_day_squares_from_the_web_sale_to_the_month_report(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 15:00:00', 'America/Lima'));
        config()->set('payments.sandbox_enabled', true);
        Mail::fake();
        Http::fake();

        [$admin, $organization, $departure] = $this->company();
        $webSeat = $this->seat($departure, 'A1', 1);
        $dockSeat = $this->seat($departure, 'A2', 2);
        $passenger = User::factory()->create(['email' => 'ana.humo@viajero.test']);
        $passenger->roles()->attach(Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente'])->id);
        $reservation = Reservation::query()->create([
            'user_id' => $passenger->id,
            'route_departure_id' => $departure->id,
            'code' => 'WEB-HUMO',
            'contact_name' => '=Ana Demo',
            'contact_email' => 'ana.humo@viajero.test',
            'contact_phone' => '999000111',
            'base_amount' => 80,
            'discount_amount' => 0,
            'total_amount' => 80,
            'status' => 'pending_payment',
            'sales_channel' => 'web',
            'expires_at' => now()->addHour(),
        ]);
        ReservationSeat::query()->create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $webSeat->id,
            'passenger_name' => '=Ana Demo',
            'document_type' => 'DNI',
            'document_number' => '44550001',
        ]);

        $this->actingAs($passenger)->post(route('payments.sandbox-confirm', 'WEB-HUMO'), ['method' => 'card'])
            ->assertRedirect(route('tickets.show', 'WEB-HUMO'));

        app(CashDesk::class)->open($admin, $organization->id, null, 0);
        $this->actingAs($admin)->post(route('company.pos.store'), [
            'departure_id' => $departure->id,
            'seat_id' => $dockSeat->id,
            'first_name' => 'Luis',
            'last_name' => 'Muelle',
            'document_type' => 'DNI',
            'document_number' => '44550002',
            'passenger_type' => 'adult',
            'hand_kg' => 5,
            'hold_kg' => 10,
            'method' => 'cash',
            'cash_tendered' => 50,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('company.parcels.store'), [
            'sender_name' => 'Rosa Quispe',
            'sender_document' => '44550003',
            'sender_phone' => '065600100',
            'recipient_name' => 'Luis Paredes',
            'recipient_document' => '44550004',
            'recipient_phone' => '065600200',
            'description' => 'Caja de fariña',
            'package_count' => 1,
            'declared_weight_kg' => 6,
            'length_cm' => 40,
            'width_cm' => 30,
            'height_cm' => 20,
            'amount' => 24,
            'method' => 'cash',
            'cash_tendered' => 24,
        ])->assertRedirect();
        $session = CashSession::query()->firstOrFail();
        $this->actingAs($admin)->post(route('company.cash-close.close', $session), [
            'counted_cash' => 74,
        ])->assertRedirect();

        $this->actingAs($passenger)->post(route('tickets.refund', 'WEB-HUMO'), [
            'reason' => 'Me arrepiento del viaje',
        ])->assertRedirect();
        $refund = TicketRefund::query()->firstOrFail();
        $this->actingAs($admin)->post(route('company.refunds.approve', $refund), [
            'amount' => '30.00',
        ])->assertRedirect();
        $super = User::factory()->create();
        $super->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super'])->id);
        $this->actingAs($super)->post(route('admin.refunds.manual', $refund), [
            'operation_number' => 'OP-HUMO-30',
        ])->assertRedirect();
        $this->actingAs($super)->post(route('admin.commissions.payouts.store', $organization), [
            'paid_on' => '2026-11-23',
            'amount' => '50.00',
            'method' => 'Transferencia',
            'operation_number' => 'OP-HUMO-50',
        ])->assertRedirect();

        $web = Payment::query()->where('provider', 'sandbox')->firstOrFail();
        $dock = Payment::query()->where('provider', 'pos')->where('purpose', 'ticket')->firstOrFail();
        $parcel = Payment::query()->where('purpose', 'cargo')->firstOrFail();
        $session->refresh();
        $this->assertSame('86.40', $web->amount);
        $this->assertSame('6.40', $web->commission_amount);
        $this->assertSame('80.00', $web->operator_net);
        $this->assertSame('50.00', $dock->amount);
        $this->assertSame('0.00', $dock->commission_amount);
        $this->assertSame('24.00', $parcel->amount);
        $this->assertSame('74.00', $session->expected_cash);
        $this->assertSame('74.00', $session->counted_cash);
        $this->assertSame('0.00', $session->difference);
        $this->assertSame('paid', $refund->fresh()->status);
        $this->assertSame('30.00', $refund->fresh()->amount);

        $report = app(CompanyReports::class)->report($organization, 'mes', '', '');
        $statement = app(CompanyCollections::class)->statement($organization, app(CompanyCollections::class)->period('mes', '2026-11-23'));
        $this->assertSame(8000, $report['channels'][0]['cents']);
        $this->assertSame(5000, $report['channels'][1]['cents']);
        $this->assertSame(0, $report['channels'][2]['cents']);
        $this->assertSame(13000, $report['total_cents']);
        $this->assertSame($report['total_cents'], array_sum(array_column($report['channels'], 'cents')));
        $this->assertSame(8000, $report['collections']['net_cents']);
        $this->assertSame(640, $report['collections']['fee_cents']);
        $this->assertSame(2400, $report['parcels']['cents']);
        $this->assertSame(3000, $report['refunds']['paid_cents']);
        $this->assertSame(0, $report['cash']['cents']);
        $this->assertSame(8000, $statement['net_cents']);
        $this->assertSame(3000, $statement['refund_cents']);
        $this->assertSame(5000, $statement['paid_cents']);
        $this->assertSame(0, $statement['pending_cents']);
        $this->assertSame($statement['net_cents'] - $statement['refund_cents'] - $statement['paid_cents'], $statement['pending_cents']);

        $page = $this->actingAs($admin)->get(route('company.reports.index', ['vista' => 'mes', 'fecha' => '2026-11-23']));
        $page->assertOk()
            ->assertSee('data-count="web">S/ 80.00', false)
            ->assertSee('data-count="counter">S/ 50.00', false)
            ->assertSee('data-count="total">S/ 130.00', false)
            ->assertSee('data-count="collections-net">S/ 80.00', false)
            ->assertSee('data-count="fee">S/ 6.40', false)
            ->assertSee('data-count="parcels">S/ 24.00', false)
            ->assertSee('data-count="refunds-paid">S/ 30.00', false)
            ->assertDontSee('S/ 86.40');
        $this->actingAs($admin)->get(route('company.collections.index', ['vista' => 'mes', 'fecha' => '2026-11-23']))
            ->assertOk()
            ->assertSee('data-count="pending">S/ 0.00<', false)
            ->assertSee('data-count="paid">S/ 50.00<', false)
            ->assertSee('OP-HUMO-50');

        $csv = $this->actingAs($admin)->get(route('company.sales.export', ['from' => '2026-11-01', 'to' => '2026-11-30']))->streamedContent();
        $this->assertStringContainsString('Boleto', $csv);
        $this->assertStringContainsString("'=Ana Demo", $csv);
        Http::assertNothingSent();
    }

    /**
     * @return array{0: User, 1: Organization, 2: RouteDeparture}
     */
    private function company(): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company',
            'legal_name' => 'Humo SAC',
            'commercial_name' => 'Humo',
            'ruc' => '20680000002',
            'email' => 'humo@test.pe',
            'status' => 'active',
            'modality' => 'fluvial',
            'commission_rate' => 8,
            'short_code' => 'HUMO',
        ]);
        $admin = User::factory()->create(['name' => 'Ada Humo']);
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);
        $origin = Port::query()->create(['name' => 'Origen Humo', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Destino Humo', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Nave Humo',
            'registration_number' => 'HUM-1',
            'vessel_type' => 'lancha',
            'seat_capacity' => 2,
            'status' => 'ready',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'code' => 'HUMO-1',
        ]);
        $departure = RouteDeparture::query()->create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addHours(5),
            'fare' => 50,
            'status' => 'scheduled',
            'is_published' => true,
        ]);

        return [$admin, $organization, $departure];
    }

    private function seat(RouteDeparture $departure, string $code, int $column): VesselSeat
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
