<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CulqiPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'payments.sandbox_enabled' => false,
            'services.culqi.enabled' => true,
            'services.culqi.environment' => 'test',
            'services.culqi.public_key' => 'pk_demo_1234',
            'services.culqi.secret_key' => 'secret_demo_1234',
            'services.culqi.webhook_secret' => 'whsec_demo',
            'services.culqi.api_url' => 'https://api.culqi.com/v2',
        ]);
    }

    public function test_a_server_priced_charge_confirms_once_and_keeps_no_card_data(): void
    {
        $reservation = $this->reservation();
        $this->fakeCulqi($this->charge('chr_test_1', 8640));

        $this->postJson(route('payments.culqi.charge', $reservation->code), ['source_id' => 'tkn_test_1'])
            ->assertOk()
            ->assertJsonPath('redirect', route('tickets.show', $reservation->code));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/charges') && $request['amount'] === 8640);
        $payment = Payment::query()->firstOrFail();
        $this->assertSame('86.40', (string) $payment->amount);
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('1111', $payment->provider_payload['last_four']);
        $this->assertStringNotContainsString('411111', json_encode($payment->provider_payload));
        $this->assertDatabaseHas('audit_logs', ['event' => 'culqi.confirmed']);

        $this->postWebhook($this->event('evt_1', 'chr_test_1', 8640, $reservation->code))->assertOk();
        $this->postWebhook($this->event('evt_2', 'chr_test_1', 8640, $reservation->code))
            ->assertOk()
            ->assertJsonPath('duplicate', true);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_webhook_confirms_the_quoted_total_and_a_repeat_does_not_duplicate(): void
    {
        $reservation = $this->reservation();
        $this->fakeCulqi($this->charge('chr_test_9', 8640));
        $event = $this->event('evt_once', 'chr_test_9', 8640, $reservation->code);

        $this->postWebhook($event)->assertOk();
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('86.40', (string) Payment::query()->firstOrFail()->amount);
        $this->assertSame(1, Ticket::query()->count());

        $this->postWebhook($event)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_webhook_for_the_fare_without_the_fee_is_refunded(): void
    {
        $reservation = $this->reservation();
        $this->fakeCulqi($this->charge('chr_test_8', 8000));

        $this->postWebhook($this->event('evt_fare', 'chr_test_8', 8000, $reservation->code))->assertOk();

        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertSame('refunded', Payment::query()->firstOrFail()->status);
        $this->assertSame(0, Ticket::query()->count());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/refunds') && $request['charge_id'] === 'chr_test_8');
    }

    public function test_a_webhook_with_the_basic_password_confirms_once(): void
    {
        $reservation = $this->reservation();
        $this->fakeCulqi($this->charge('chr_test_7', 8640));
        $raw = json_encode($this->event('evt_basic', 'chr_test_7', 8640, $reservation->code), JSON_UNESCAPED_UNICODE);

        $this->call('POST', '/webhooks/culqi', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Basic '.base64_encode('culqi:whsec_demo'),
        ], $raw)->assertOk();

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_an_invalid_signature_changes_nothing(): void
    {
        $reservation = $this->reservation();
        $this->fakeCulqi($this->charge('chr_test_1', 8640));

        $this->postWebhook($this->event('evt_bad', 'chr_test_1', 8640, $reservation->code), 'firma-invalida')
            ->assertUnauthorized();

        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_a_manipulated_amount_is_rejected_before_calling_culqi(): void
    {
        $reservation = $this->reservation();
        Http::fake();

        $this->postJson(route('payments.culqi.charge', $reservation->code), [
            'source_id' => 'tkn_test_1',
            'amount' => 100,
        ])->assertStatus(422)->assertJsonPath('message', __('payments.amount_rejected'));

        Http::assertNothingSent();
        $this->assertSame('pending_payment', $reservation->fresh()->status);
    }

    public function test_a_charge_for_a_different_amount_is_refunded(): void
    {
        $reservation = $this->reservation();
        $this->fakeCulqi($this->charge('chr_test_2', 100));

        $this->postJson(route('payments.culqi.charge', $reservation->code), ['source_id' => 'tkn_test_2'])
            ->assertStatus(422);

        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertSame('refunded', Payment::query()->firstOrFail()->status);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/refunds') && $request['amount'] === 100 && $request['reason'] === 'solicitud_comprador');
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_an_expired_reservation_is_not_charged(): void
    {
        $reservation = $this->reservation();
        $reservation->update(['expires_at' => now()->subMinute()]);
        Http::fake();

        $this->postJson(route('payments.culqi.charge', $reservation->code), ['source_id' => 'tkn_test_1'])
            ->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_charge_captured_after_expiry_is_refunded_and_not_confirmed(): void
    {
        $reservation = $this->reservation();
        Http::fake(function (Request $request) use ($reservation) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/charges')) {
                $reservation->update(['expires_at' => now()->subMinute()]);

                return Http::response($this->charge('chr_test_3', 8640), 201);
            }
            if (str_contains($request->url(), '/refunds')) {
                return Http::response(['object' => 'refund', 'id' => 'ref_test_3'], 201);
            }

            return Http::response(['user_message' => 'No'], 404);
        });

        $this->postJson(route('payments.culqi.charge', $reservation->code), ['source_id' => 'tkn_test_3'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('payments.expired_returned'));

        $this->assertNotSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('refunded', Payment::query()->firstOrFail()->status);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_a_culqi_error_leaves_the_reservation_unconfirmed(): void
    {
        $reservation = $this->reservation();
        Http::fake([
            'https://api.culqi.com/*' => Http::response(['user_message' => 'La tarjeta no tiene fondos.'], 402),
        ]);

        $this->postJson(route('payments.culqi.charge', $reservation->code), ['source_id' => 'tkn_test_1'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La tarjeta no tiene fondos.');

        $this->assertSame('pending_payment', $reservation->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_the_card_button_is_hidden_without_keys_and_the_test_banner_follows_the_environment(): void
    {
        $reservation = $this->reservation();
        config([
            'services.culqi.enabled' => true,
            'services.culqi.public_key' => null,
            'services.culqi.secret_key' => null,
        ]);

        $this->get(route('payments.show', $reservation->code))
            ->assertOk()
            ->assertDontSee('id="culqiPayButton"', false)
            ->assertDontSee('js.culqi.com', false)
            ->assertDontSee(__('payments.test_mode'));

        config([
            'services.culqi.public_key' => 'pk_demo_1234',
            'services.culqi.secret_key' => 'secret_demo_1234',
            'services.culqi.environment' => 'test',
        ]);
        $this->get(route('payments.show', $reservation->code))
            ->assertOk()
            ->assertSee(__('payments.test_mode'))
            ->assertSee('id="culqiPayButton"', false)
            ->assertDontSee('secret_demo_1234', false);

        config(['services.culqi.environment' => 'production']);
        $this->get(route('payments.show', $reservation->code))
            ->assertOk()
            ->assertDontSee(__('payments.test_mode'))
            ->assertSee('id="culqiPayButton"', false);
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function fakeCulqi(array $charge): void
    {
        Http::fake(function (Request $request) use ($charge) {
            if (str_contains($request->url(), '/refunds')) {
                return Http::response(['object' => 'refund', 'id' => 'ref_test_1'], 201);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/charges')) {
                return Http::response($charge, 201);
            }
            if (str_contains($request->url(), '/charges/')) {
                return Http::response($charge, 200);
            }

            return Http::response(['user_message' => 'No'], 404);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function charge(string $id, int $cents): array
    {
        return [
            'object' => 'charge',
            'id' => $id,
            'amount' => $cents,
            'currency_code' => 'PEN',
            'state' => 'Exitosa',
            'email' => 'ana@viajero.test',
            'source' => [
                'id' => 'tkn_test_1',
                'brand' => 'Visa',
                'last_four' => '1111',
                'card_number' => '411111******1111',
                'cvv' => '123',
            ],
            'metadata' => ['reservation_code' => 'NY-CULQI'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $eventId, string $chargeId, int $cents, string $code): array
    {
        $charge = $this->charge($chargeId, $cents);
        $charge['metadata'] = ['reservation_code' => $code];

        return [
            'object' => 'event',
            'id' => $eventId,
            'type' => 'charge.creation.succeeded',
            'data' => $charge,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature ??= hash_hmac('sha256', $raw, (string) config('services.culqi.webhook_secret'));

        return $this->call('POST', '/webhooks/culqi', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CULQI_SIGNATURE' => $signature,
        ], $raw);
    }

    private function reservation(): Reservation
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => 'Culqi SAC', 'commercial_name' => 'Culqi SAC',
            'ruc' => '20671000801', 'email' => '20671000801@test.pe', 'status' => 'active', 'modality' => 'fluvial', 'commission_rate' => 8,
        ]);
        $origin = Port::query()->create(['name' => 'Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => 'Nave Culqi', 'registration_number' => 'MAT-CULQI',
            'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'CULQI-1',
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
            'code' => 'NY-CULQI',
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
}
