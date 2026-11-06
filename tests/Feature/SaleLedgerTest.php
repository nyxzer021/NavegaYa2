<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\AuditLog;
use App\Models\BusinessConversation;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Services\BookingPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SaleLedgerTest extends TestCase
{
    use RefreshDatabase;

    private int $rucSequence = 20600000000;

    public function test_past_departures_are_closed_and_future_ones_stay_open(): void
    {
        [$past] = $this->departure('PAS-01', now()->subDays(3), now()->subDays(2));
        [$future] = $this->departure('FUT-01', now()->addDay(), now()->addDays(2));
        [$cancelled] = $this->departure('CAN-01', now()->subDays(4), now()->subDays(3), 'cancelled');
        [$rescheduled] = $this->departure('REA-01', now()->subDay(), now()->subHours(2), 'rescheduled');
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Aero Cierre SAC', 'ruc' => '20101010101', 'email' => 'aero-cierre@test.pe', 'status' => 'active']);
        $aircraft = Aircraft::create(['organization_id' => $company->id, 'name' => 'Caravan', 'registration_number' => 'OB-CLOSE', 'seat_capacity' => 8, 'status' => 'ready']);
        $route = AirRoute::create(['organization_id' => $company->id, 'origin_city' => 'Iquitos', 'destination_city' => 'Yurimaguas', 'code' => 'AIR-CLOSE', 'estimated_duration_minutes' => 80]);
        $air = AirDeparture::create(['air_route_id' => $route->id, 'aircraft_id' => $aircraft->id, 'departure_at' => now()->subHours(8), 'estimated_arrival_at' => now()->subHours(5), 'fare' => 300, 'status' => 'scheduled', 'is_published' => true]);

        $this->artisan('navegaya:close-past-departures')->assertSuccessful();

        $this->assertSame('completed', $past->fresh()->status);
        $this->assertFalse((bool) $past->fresh()->is_published);
        $this->assertSame('scheduled', $future->fresh()->status);
        $this->assertTrue((bool) $future->fresh()->is_published);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('rescheduled', $rescheduled->fresh()->status);
        $this->assertSame('completed', $air->fresh()->status);
        $this->assertFalse((bool) $air->fresh()->is_published);

        [$stillAtDock] = $this->departure('MUELLE-01', now()->subHour(), now()->addHours(4));
        $stillAtDock->update(['estimated_arrival_at' => null, 'departure_at' => now()->subHour()]);
        $this->artisan('navegaya:close-past-departures')->assertSuccessful();
        $this->assertSame('scheduled', $stillAtDock->fresh()->status);
    }

    public function test_one_sale_keeps_the_same_charged_amount_and_commission(): void
    {
        Mail::fake();
        config()->set('payments.sandbox_enabled', true);
        [$departure, $seat] = $this->departure('VEN-01', now()->addDay(), now()->addDays(2));
        $departure->transportRoute->organization->update(['commission_rate' => 8]);
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-VENTA',
            'contact_name' => 'Ana Torres',
            'contact_email' => 'ana@test.pe',
            'contact_phone' => '999888777',
            'total_amount' => 180,
            'status' => 'pending_payment',
            'sales_channel' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);
        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Ana Torres',
            'document_number' => '12345678',
        ]);

        $this->post(route('payments.sandbox-confirm', $reservation->code), ['method' => 'yape'])->assertRedirect();

        $reservation->refresh();
        $payment = Payment::where('reservation_id', $reservation->id)->firstOrFail();
        $this->assertSame('180.00', $reservation->base_amount);
        $this->assertSame('14.40', $reservation->service_fee_amount);
        $this->assertSame('194.40', $reservation->total_amount);
        $this->assertSame('194.40', $payment->amount);
        $this->assertSame('8.00', $payment->commission_rate);
        $this->assertSame('14.40', $payment->commission_amount);
        $this->assertSame('180.00', $payment->operator_net);
        $this->assertSame('passenger_fee', $payment->provider_payload['commission_model']);

        $departure->update(['fare' => 200]);
        $this->assertSame('194.40', $reservation->fresh()->total_amount);
        $this->assertSame('14.40', $payment->fresh()->commission_amount);
        $this->assertSame('180.00', $payment->fresh()->operator_net);
    }

    public function test_marketplace_gmv_uses_ticket_payments_and_the_saved_agreement(): void
    {
        $company = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Selva Fast SAC',
            'commercial_name' => 'Selva Fast',
            'ruc' => '20900000001',
            'email' => 'selva@test.pe',
            'status' => 'active',
            'commission_rate' => 8,
        ]);
        [$departure, $seat] = $this->departure('GMV-01', now()->addDay(), now()->addDays(2), 'scheduled', $company);
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-GMV',
            'contact_name' => 'Luis Pago',
            'contact_email' => 'luis@test.pe',
            'contact_phone' => '999',
            'total_amount' => 180,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Luis Pago',
            'document_number' => '87654321',
        ]);
        Payment::create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => 'yape',
            'provider' => 'sandbox',
            'provider_reference' => 'DEMO-GMV',
            'amount' => 198,
            'commission_rate' => 8,
            'commission_amount' => 14.40,
            'operator_net' => 183.60,
            'status' => 'confirmed',
            'paid_at' => now(),
            'provider_payload' => ['commission_model' => 'passenger_fee'],
        ]);
        Payment::create([
            'purpose' => 'subscription',
            'method' => 'card',
            'provider' => 'sandbox',
            'provider_reference' => 'DEMO-SUB',
            'amount' => 1500,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador']));

        $this->actingAs($admin)->get(route('admin.commissions.index'))
            ->assertOk()
            ->assertSee('198.00')
            ->assertSee('14.40')
            ->assertDontSee('1,500.00');

        $this->actingAs($admin)->get(route('admin.sales.index'))
            ->assertOk()
            ->assertSee('198.00')
            ->assertDontSee('180.00')
            ->assertDontSee('1,500.00');
    }

    public function test_operator_without_future_departures_is_not_on_sale(): void
    {
        Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Sin Salidas SAC',
            'commercial_name' => 'Sin Salidas',
            'ruc' => '20900000002',
            'email' => 'sinsalidas@test.pe',
            'status' => 'active',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador']));

        $this->actingAs($admin)->get(route('admin.itineraries.index'))
            ->assertOk()
            ->assertSee('Sin Salidas')
            ->assertSee('Sin inventario')
            ->assertDontSee('● En venta');
    }

    public function test_view_counters_and_read_marks_are_not_audited(): void
    {
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => 'Auditoría SAC', 'ruc' => '20900000003', 'email' => 'audit@test.pe', 'status' => 'active']);
        $advertisement = Advertisement::create([
            'organization_id' => $organization->id,
            'business_name' => 'Lodge',
            'business_type' => 'lodge_hotel',
            'contact_name' => 'Ana',
            'phone_whatsapp' => '999999999',
            'placements' => ['home_carousel'],
            'category' => 'Lodge / hotel',
            'title' => 'Título original',
            'description' => 'Descripción',
            'target_url' => 'https://example.test',
            'monthly_fee' => 0,
            'status' => 'active',
            'views' => 0,
            'clicks' => 0,
        ]);
        $conversation = BusinessConversation::create(['organization_id' => $organization->id, 'subject' => 'Consulta']);

        $advertisement->update(['views' => 9, 'clicks' => 2]);
        $conversation->update(['admin_last_read_at' => now()]);

        $this->assertSame(0, AuditLog::query()->where('event', 'updated')->where('subject_type', Advertisement::class)->where('subject_id', $advertisement->id)->count());
        $this->assertSame(0, AuditLog::query()->where('event', 'updated')->where('subject_type', BusinessConversation::class)->where('subject_id', $conversation->id)->count());

        $advertisement->update(['title' => 'Título corregido']);

        $this->assertSame(1, AuditLog::query()->where('event', 'updated')->where('subject_type', Advertisement::class)->where('subject_id', $advertisement->id)->count());
    }

    public function test_confirmed_ticket_shows_the_company_whatsapp(): void
    {
        Mail::fake();
        config()->set('payments.sandbox_enabled', true);
        [$departure, $seat] = $this->departure('WHA-01', now()->addDay(), now()->addDays(2));
        $departure->transportRoute->organization->update([
            'whatsapp' => '953540705',
            'show_public_whatsapp' => true,
        ]);
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-WHA',
            'contact_name' => 'Ana Torres',
            'contact_email' => 'ana-wa@test.pe',
            'contact_phone' => '999888777',
            'total_amount' => 100,
            'status' => 'pending_payment',
            'sales_channel' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);
        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Ana Torres',
            'document_number' => '12345678',
        ]);

        $this->post(route('payments.sandbox-confirm', $reservation->code), ['method' => 'yape'])->assertRedirect();

        $this->get(route('tickets.show', $reservation->code))
            ->assertOk()
            ->assertSee('https://wa.me/51953540705', false)
            ->assertSee('WhatsApp boletería');
    }

    public function test_dashboard_separates_ticket_commission_from_advertising_income(): void
    {
        $company = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Ingresos SAC',
            'commercial_name' => 'Ingresos',
            'ruc' => '20900000088',
            'email' => 'ingresos@test.pe',
            'status' => 'active',
            'commission_rate' => 8,
        ]);
        [$departure, $seat] = $this->departure('ING-01', now()->addDay(), now()->addDays(2), 'scheduled', $company);
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-ING',
            'contact_name' => 'Luis',
            'contact_email' => 'luis-ing@test.pe',
            'contact_phone' => '999',
            'total_amount' => 180,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Luis',
            'document_number' => '87654321',
        ]);
        Payment::create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => 'yape',
            'provider' => 'sandbox',
            'provider_reference' => 'DEMO-ING',
            'amount' => 198,
            'commission_rate' => 8,
            'commission_amount' => 14.40,
            'operator_net' => 183.60,
            'status' => 'confirmed',
            'paid_at' => now(),
            'provider_payload' => ['commission_model' => 'passenger_fee'],
        ]);
        Payment::create([
            'purpose' => 'subscription',
            'method' => 'card',
            'provider' => 'sandbox',
            'provider_reference' => 'DEMO-ADS',
            'amount' => 750,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador']));

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ingreso NavegaYA')
            ->assertSee('14.40')
            ->assertSee('Anuncios de empresas')
            ->assertSee('750.00');
    }

    public function test_counter_sale_does_not_add_the_web_service_fee(): void
    {
        Mail::fake();
        [$departure, $seat] = $this->departure('POS-01', now(), now()->addHours(4));
        $departure->transportRoute->organization->update(['physical_sales_commission_rate' => 0, 'commission_rate' => 8]);
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'POS-TEST',
            'contact_name' => 'Muelle',
            'contact_email' => 'pos-test@pos.navegaya.test',
            'contact_phone' => '999',
            'total_amount' => 180,
            'status' => 'pending_payment',
            'sales_channel' => 'counter',
        ]);
        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Muelle',
            'document_number' => '11112222',
        ]);

        app(BookingPaymentService::class)->confirm($reservation, [
            'method' => 'cash_dock',
            'provider' => 'pos',
            'provider_reference' => 'CASH-POS-TEST',
        ], 0);

        $reservation->refresh();
        $this->assertSame('180.00', $reservation->total_amount);
        $this->assertSame('0.00', $reservation->service_fee_amount);
        $this->assertSame('0.00', Payment::firstOrFail()->commission_amount);
        $this->assertSame('180.00', Payment::firstOrFail()->operator_net);
    }

    public function test_passenger_pays_the_agreement_and_the_company_keeps_the_fare(): void
    {
        Mail::fake();
        [$departure, $seat] = $this->departure('TAR-100', now()->addDay(), now()->addDays(2));
        $departure->transportRoute->organization->update(['commission_rate' => 8]);
        $departure->update(['fare' => 100]);
        $legacy = Payment::create([
            'reservation_id' => $this->heldReservation($departure, $seat, 'NY-VIEJA', 100)->id,
            'purpose' => 'ticket',
            'method' => 'yape',
            'provider' => 'sandbox',
            'provider_reference' => 'VIEJA-100',
            'amount' => 108,
            'commission_rate' => 8,
            'commission_amount' => 8.64,
            'operator_net' => 99.36,
            'status' => 'confirmed',
            'paid_at' => now()->subDay(),
        ]);

        $web = $this->heldReservation($departure, $this->extraSeat($departure, '1B'), 'NY-WEB-100', 100);
        app(BookingPaymentService::class)->confirm($web, [
            'method' => 'yape', 'provider' => 'sandbox', 'provider_reference' => 'WEB-100',
        ]);
        $counter = $this->heldReservation($departure, $this->extraSeat($departure, '1C'), 'NY-MUELLE-100', 100, 'counter');
        app(BookingPaymentService::class)->confirm($counter, [
            'method' => 'cash_dock', 'provider' => 'pos', 'provider_reference' => 'MUELLE-100',
        ]);
        $discounted = $this->heldReservation($departure, $this->extraSeat($departure, '1D'), 'NY-DESC-100', 100);
        $discounted->update(['discount_amount' => 10]);
        app(BookingPaymentService::class)->confirm($discounted, [
            'method' => 'yape', 'provider' => 'sandbox', 'provider_reference' => 'DESC-100',
        ]);

        $webPayment = Payment::where('provider_reference', 'WEB-100')->firstOrFail();
        $this->assertSame('108.00', $web->fresh()->total_amount);
        $this->assertSame('8.00', $webPayment->commission_amount);
        $this->assertSame('100.00', $webPayment->operator_net);

        $counterPayment = Payment::where('provider_reference', 'MUELLE-100')->firstOrFail();
        $this->assertSame('100.00', $counter->fresh()->total_amount);
        $this->assertSame('0.00', $counter->fresh()->service_fee_amount);
        $this->assertSame('0.00', $counterPayment->commission_amount);
        $this->assertSame('100.00', $counterPayment->operator_net);

        $discountPayment = Payment::where('provider_reference', 'DESC-100')->firstOrFail();
        $this->assertSame('7.20', $discounted->fresh()->service_fee_amount);
        $this->assertSame('97.20', $discounted->fresh()->total_amount);
        $this->assertSame('7.20', $discountPayment->commission_amount);
        $this->assertSame('90.00', $discountPayment->operator_net);

        $this->assertSame('8.64', $legacy->fresh()->commission_amount);
        $this->assertSame('99.36', $legacy->fresh()->operator_net);
        $this->assertNull(data_get($legacy->fresh()->provider_payload, 'commission_model'));
    }

    private function heldReservation(RouteDeparture $departure, VesselSeat $seat, string $code, float $amount, string $channel = 'web'): Reservation
    {
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => $code,
            'contact_name' => 'Pasajero',
            'contact_email' => strtolower($code).'@test.pe',
            'contact_phone' => '999000111',
            'total_amount' => $amount,
            'status' => 'pending_payment',
            'sales_channel' => $channel,
            'expires_at' => now()->addMinutes(10),
        ]);
        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Pasajero',
            'document_number' => '12345678',
        ]);

        return $reservation;
    }

    private function extraSeat(RouteDeparture $departure, string $code): VesselSeat
    {
        return VesselSeat::create([
            'vessel_id' => $departure->vessel_id,
            'code' => $code,
            'deck' => 'principal',
            'seat_class' => 'standard',
            'row_position' => 1,
            'column_position' => ord(substr($code, -1)) - 64,
        ]);
    }

    /**
     * @return array{0: RouteDeparture, 1: VesselSeat}
     */
    private function departure(string $code, $departureAt, $arrivalAt, string $status = 'scheduled', ?Organization $company = null): array
    {
        $company ??= Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Río '.$code,
            'ruc' => (string) $this->rucSequence++,
            'email' => strtolower($code).'@test.pe',
            'status' => 'active',
        ]);
        $origin = Port::create(['name' => 'Origen '.$code, 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Destino '.$code, 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Lancha '.$code, 'registration_number' => $code, 'vessel_type' => 'lancha', 'seat_capacity' => 4, 'status' => 'ready']);
        $seat = VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1A', 'deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1]);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => $code]);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => $departureAt,
            'estimated_arrival_at' => $arrivalAt,
            'fare' => 180,
            'status' => $status,
            'is_published' => true,
        ]);

        return [$departure, $seat];
    }
}
