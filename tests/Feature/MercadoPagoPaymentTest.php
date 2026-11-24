<?php

namespace Tests\Feature;

use App\Models\CulqiSettlementLine;
use App\Models\Organization;
use App\Models\Payment;
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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MercadoPagoPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'payments.sandbox_enabled' => false,
            'services.culqi.enabled' => false,
            'services.mercadopago.enabled' => true,
            'services.mercadopago.environment' => 'test',
            'services.mercadopago.public_key' => 'pub_demo_1234',
            'services.mercadopago.access_token' => 'token_demo_1234',
            'services.mercadopago.webhook_secret' => 'whsec_mp_demo',
            'services.mercadopago.api_url' => 'https://api.mercadopago.com',
        ]);
    }

    public function test_checkout_uses_the_server_amount_and_one_payment_confirms_once(): void
    {
        $reservation = $this->reservation();
        $this->fakeMercadoPago($this->paymentBody($reservation->code, 86.4));

        $this->post(route('payments.mercadopago.start', $reservation->code))
            ->assertRedirect('https://sandbox.mercadopago.com.pe/checkout/v1/redirect?pref_id=pref-1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/checkout/preferences')
            && $request['items'][0]['unit_price'] === 86.4
            && $request['external_reference'] === $reservation->code);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame('pending_payment', $reservation->fresh()->status);

        $this->get(route('payments.mercadopago.return', $reservation->code).'?payment_id=1001')
            ->assertRedirect(route('tickets.show', $reservation->code));
        $payment = Payment::query()->firstOrFail();
        $this->assertSame('86.40', (string) $payment->amount);
        $this->assertSame('mp_1001', $payment->provider_reference);
        $this->assertSame('mercadopago', $payment->provider);
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('4242', $payment->provider_payload['last_four']);
        $stored = json_encode($payment->provider_payload);
        $this->assertStringNotContainsString('4111111111111111', (string) $stored);
        $this->assertStringNotContainsString('ana@viajero.test', (string) $stored);
        $this->assertStringNotContainsString('token_demo_1234', (string) $stored);
        $this->assertDatabaseHas('audit_logs', ['event' => 'mp.confirmed']);

        $this->postWebhook('1001')->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_the_same_webhook_twice_does_not_duplicate(): void
    {
        $reservation = $this->reservation();
        $this->fakeMercadoPago($this->paymentBody($reservation->code, 86.4));

        $this->postWebhook('1001')->assertOk();
        $this->postWebhook('1001')->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_an_invalid_signature_changes_nothing(): void
    {
        $reservation = $this->reservation();
        Http::fake();

        $this->call('POST', '/webhooks/mercadopago?data.id=1001&type=payment', [], [], [], [
            'HTTP_X_SIGNATURE' => 'ts=1700000000,v1='.str_repeat('ab', 32),
            'HTTP_X_REQUEST_ID' => 'req-mp-1',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}')->assertUnauthorized();

        Http::assertNothingSent();
        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_different_amount_is_refunded_and_does_not_confirm(): void
    {
        $reservation = $this->reservation();
        $this->fakeMercadoPago($this->paymentBody($reservation->code, 100));

        $this->postWebhook('1001')->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/v1/payments/1001/refunds') && (float) $request['amount'] === 100.0);
        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame('refunded', Payment::query()->firstOrFail()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'mp.returned']);
    }

    public function test_an_expired_reservation_is_refunded(): void
    {
        $reservation = $this->reservation();
        $reservation->update(['expires_at' => now()->subMinute()]);
        $this->fakeMercadoPago($this->paymentBody($reservation->code, 86.4));

        $this->postWebhook('1001')->assertOk();

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/refunds'));
        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame('refunded', Payment::query()->firstOrFail()->status);
    }

    public function test_a_provider_error_leaves_the_reservation_unconfirmed(): void
    {
        $reservation = $this->reservation();
        Http::fake([
            'https://api.mercadopago.com/*' => Http::response(['message' => 'El medio no está disponible.'], 422),
        ]);

        $this->from(route('payments.show', $reservation->code))
            ->post(route('payments.mercadopago.start', $reservation->code))
            ->assertRedirect(route('payments.show', $reservation->code))
            ->assertSessionHas('payment_error', 'El medio no está disponible.');

        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_the_passenger_sees_only_the_providers_that_are_on(): void
    {
        $reservation = $this->reservation();
        config([
            'services.culqi.enabled' => false,
            'services.culqi.public_key' => null,
            'services.culqi.secret_key' => null,
            'services.mercadopago.enabled' => false,
            'services.mercadopago.access_token' => null,
        ]);

        $this->get(route('payments.show', $reservation->code))
            ->assertOk()
            ->assertDontSee('id="culqiPayButton"', false)
            ->assertDontSee('id="mercadopagoPayButton"', false)
            ->assertDontSee('js.culqi.com', false)
            ->assertDontSee(__('payments.test_mode'))
            ->assertSee(__('payments.offline'));

        config([
            'services.mercadopago.enabled' => true,
            'services.mercadopago.access_token' => 'token_demo_1234',
            'services.mercadopago.environment' => 'test',
        ]);
        $this->get(route('payments.show', $reservation->code))
            ->assertOk()
            ->assertSee('id="mercadopagoPayButton"', false)
            ->assertSee(__('payments.test_mode'))
            ->assertDontSee('id="culqiPayButton"', false)
            ->assertDontSee('token_demo_1234', false);

        config([
            'services.culqi.enabled' => true,
            'services.culqi.public_key' => 'pk_demo_1234',
            'services.culqi.secret_key' => 'secret_demo_1234',
            'services.culqi.environment' => 'production',
            'services.mercadopago.environment' => 'production',
        ]);
        $this->get(route('payments.show', $reservation->code))
            ->assertOk()
            ->assertSee('id="culqiPayButton"', false)
            ->assertSee('id="mercadopagoPayButton"', false)
            ->assertDontSee(__('payments.test_mode'));
    }

    public function test_connection_screen_masks_the_token_and_probe_is_read_only(): void
    {
        config([
            'services.mercadopago.public_key' => 'pub_demo_ABCD9ZQ4',
            'services.mercadopago.access_token' => 'token_demo_77LM',
            'services.mercadopago.webhook_secret' => 'whsec_mp_3KX8',
        ]);
        Http::fake(['https://api.mercadopago.com/users/me' => Http::response(['id' => 1], 200)]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.payment-connections.index'))
            ->assertOk()
            ->assertSee('Presente ····9ZQ4')
            ->assertSee('Presente ····77LM')
            ->assertSee('Presente ····3KX8')
            ->assertSee(route('webhooks.mercadopago'))
            ->assertDontSee('pub_demo_ABCD9ZQ4')
            ->assertDontSee('token_demo_77LM')
            ->assertDontSee('whsec_mp_3KX8');

        $this->from(route('admin.payment-connections.index'))
            ->followingRedirects()
            ->post(route('admin.payment-connections.probe'), ['provider' => 'mercadopago'])
            ->assertOk()
            ->assertSee('La conexión respondió. No se creó ningún cobro.')
            ->assertDontSee('token_demo_77LM');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_ends_with($request->url(), '/users/me'));
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_a_matching_mercado_pago_charge_enters_the_month_and_is_not_imported_twice(): void
    {
        Payment::query()->create([
            'purpose' => 'ticket',
            'method' => 'card',
            'provider' => 'mercadopago',
            'provider_reference' => 'mp_1001',
            'amount' => '86.40',
            'commission_rate' => '8.00',
            'commission_amount' => '6.40',
            'operator_net' => '80.00',
            'commission_status' => 'pending',
            'status' => 'confirmed',
            'paid_at' => '2026-11-18 15:00:00',
            'provider_payload' => ['commission_model' => 'passenger_fee'],
        ]);
        Http::fake([
            'https://api.mercadopago.com/*' => Http::response([
                'paging' => ['total' => 1],
                'results' => [[
                    'id' => 1001,
                    'status' => 'approved',
                    'transaction_amount' => 86.4,
                    'currency_id' => 'PEN',
                    'date_created' => '2026-11-18T10:00:00.000-05:00',
                    'fee_details' => [['type' => 'mercadopago_fee', 'amount' => 2.59]],
                    'card' => ['last_four_digits' => '4242', 'card_number' => '4111111111111111'],
                    'payer' => ['email' => 'ana@viajero.test'],
                ]],
            ], 200),
        ]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->from(route('admin.reconciliation.index', ['mes' => '2026-11']))
            ->followingRedirects()
            ->post(route('admin.reconciliation.fetch'), ['mes' => '2026-11', 'provider' => 'mercadopago'])
            ->assertOk()
            ->assertSee('Se trajeron 1 cargos.')
            ->assertSee('86.40')
            ->assertSee('6.40')
            ->assertSee('2.59')
            ->assertSee('80.00')
            ->assertSee('3.81')
            ->assertSee('Sin diferencias')
            ->assertDontSee('4111111111111111');

        $this->assertSame(1, CulqiSettlementLine::query()->count());
        $stored = json_encode(DB::table('culqi_settlement_lines')->get()).json_encode(DB::table('audit_logs')->get());
        $this->assertStringNotContainsString('4111111111111111', (string) $stored);
        $this->assertStringNotContainsString('ana@viajero.test', (string) $stored);

        $this->post(route('admin.reconciliation.fetch'), ['mes' => '2026-11', 'provider' => 'mercadopago'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Se trajeron 0 cargos. 1 ya estaban.');
        $this->assertSame(1, CulqiSettlementLine::query()->count());
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function fakeMercadoPago(array $payment): void
    {
        Http::fake(function (Request $request) use ($payment) {
            if (str_contains($request->url(), '/checkout/preferences')) {
                return Http::response([
                    'id' => 'pref-1',
                    'external_reference' => $payment['external_reference'],
                    'items' => [['unit_price' => 86.4, 'quantity' => 1]],
                    'sandbox_init_point' => 'https://sandbox.mercadopago.com.pe/checkout/v1/redirect?pref_id=pref-1',
                    'init_point' => 'https://www.mercadopago.com.pe/checkout/v1/redirect?pref_id=pref-1',
                ], 201);
            }
            if (str_contains($request->url(), '/refunds')) {
                return Http::response(['id' => 9001, 'status' => 'approved', 'amount' => $request['amount']], 201);
            }
            if (preg_match('#/v1/payments/\d+$#', $request->url()) === 1) {
                return Http::response($payment, 200);
            }

            return Http::response(['message' => 'No'], 404);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentBody(string $code, float $amount): array
    {
        return [
            'id' => 1001,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'transaction_amount' => $amount,
            'currency_id' => 'PEN',
            'external_reference' => $code,
            'payment_type_id' => 'credit_card',
            'payment_method_id' => 'visa',
            'card' => ['last_four_digits' => '4242', 'card_number' => '4111111111111111'],
            'payer' => ['email' => 'ana@viajero.test'],
        ];
    }

    private function postWebhook(string $paymentId): TestResponse
    {
        $requestId = 'req-mp-1';
        $ts = '1700000000';
        $signature = hash_hmac('sha256', 'id:'.$paymentId.';request-id:'.$requestId.';ts:'.$ts.';', (string) config('services.mercadopago.webhook_secret'));

        return $this->call('POST', '/webhooks/mercadopago?data.id='.$paymentId.'&type=payment', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => 'ts='.$ts.',v1='.$signature,
            'HTTP_X_REQUEST_ID' => $requestId,
        ], '{}');
    }

    private function reservation(): Reservation
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => 'Mercado SAC', 'commercial_name' => 'Mercado SAC',
            'ruc' => '20671000821', 'email' => '20671000821@test.pe', 'status' => 'active', 'modality' => 'fluvial', 'commission_rate' => 8,
        ]);
        $origin = Port::query()->create(['name' => 'Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => 'Nave Mercado', 'registration_number' => 'MAT-MP01',
            'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'MP-1',
        ]);
        $departure = RouteDeparture::query()->create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(),
            'fare' => 80, 'status' => 'scheduled', 'is_published' => true,
        ]);
        $seat = VesselSeat::query()->create([
            'vessel_id' => $vessel->id, 'code' => 'A1', 'deck' => 'principal', 'row_position' => 1, 'column_position' => 1,
        ]);
        $reservation = Reservation::query()->create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-MPAGO',
            'contact_name' => 'Ana Web',
            'contact_email' => 'ana@viajero.test',
            'contact_phone' => '999000111',
            'base_amount' => '80.00',
            'discount_amount' => '0.00',
            'total_amount' => '80.00',
            'status' => 'pending_payment',
            'sales_channel' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);
        ReservationSeat::query()->create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Ana Web',
            'document_number' => '12345678',
        ]);

        return $reservation;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super'])->id);

        return $user;
    }
}
