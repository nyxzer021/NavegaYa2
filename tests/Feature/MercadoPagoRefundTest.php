<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TicketRefund;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\WebFareRefund;
use App\Services\TicketRefunds;
use App\Support\RefundRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mercadopago.enabled' => true,
            'services.mercadopago.access_token' => 'token_demo_1234',
            'services.mercadopago.api_url' => 'https://api.mercadopago.com',
        ]);
    }

    public function test_an_approved_web_refund_is_sent_once(): void
    {
        [, $refund] = $this->approvedWebRefund();
        $super = $this->superAdmin();
        Http::fake([
            'https://api.mercadopago.com/*' => Http::response(['id' => 9001, 'status' => 'approved', 'amount' => 86.4], 201),
        ]);

        $this->actingAs($super)->post(route('admin.refunds.card', $refund))
            ->assertRedirect()
            ->assertSessionHas('success', __('refunds.card_done'));

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/v1/payments/1001/refunds')
            && (float) $request['amount'] === 86.4
            && $request->hasHeader('X-Idempotency-Key'));
        $this->assertSame('paid', $refund->fresh()->status);
        $this->assertSame('mpr_9001', $refund->fresh()->provider_refund_id);
        $this->assertSame('86.40', (string) $refund->fresh()->amount);
        $this->assertSame(1, WebFareRefund::query()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'refund.card']);

        $this->actingAs($super)->post(route('admin.refunds.card', $refund))->assertSessionHasErrors('refund');
        Http::assertSentCount(1);
    }

    public function test_a_refund_above_the_charge_is_rejected_before_calling_mercado_pago(): void
    {
        [, $refund] = $this->approvedWebRefund();
        $refund->update(['amount' => '90.00']);
        Http::fake();

        $this->actingAs($this->superAdmin())->post(route('admin.refunds.card', $refund))
            ->assertSessionHasErrors('refund');

        Http::assertNothingSent();
        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertNull($refund->fresh()->provider_refund_id);
    }

    public function test_a_failure_keeps_the_refund_pending_and_manual_payment_still_works(): void
    {
        [, $refund] = $this->approvedWebRefund();
        Http::fake([
            'https://api.mercadopago.com/*' => Http::response(['message' => 'El cargo ya no admite reembolso.'], 422),
        ]);
        $super = $this->superAdmin();

        $this->actingAs($super)->post(route('admin.refunds.card', $refund))
            ->assertSessionHasErrors(['refund' => 'El cargo ya no admite reembolso.']);
        $this->assertSame('approved', $refund->fresh()->status);

        $this->actingAs($super)->post(route('admin.refunds.manual', $refund), ['operation_number' => 'OP-9988'])
            ->assertRedirect()
            ->assertSessionHas('success', __('refunds.paid_flash'));
        $this->assertSame('paid', $refund->fresh()->status);
        $this->assertSame('OP-9988', $refund->fresh()->operation_number);
        $this->assertNull($refund->fresh()->provider_refund_id);
        Http::assertSentCount(1);
    }

    public function test_only_the_super_admin_can_refund(): void
    {
        [$admin, $refund] = $this->approvedWebRefund();
        $platform = User::factory()->create();
        $platform->roles()->attach(Role::firstOrCreate(['code' => 'admin'], ['name' => 'Administrador'])->id);
        Http::fake();

        $this->actingAs($admin)->post(route('admin.refunds.card', $refund))->assertForbidden();
        $this->actingAs($platform)->post(route('admin.refunds.card', $refund))->assertForbidden();
        $this->actingAs($platform)->post(route('admin.refunds.manual', $refund), ['operation_number' => 'OP-1'])->assertForbidden();
        $this->actingAs($this->superAdmin())->get(route('admin.refunds.index'))
            ->assertOk()
            ->assertSee(__('refunds.card_button'))
            ->assertSee(__('refunds.other_means'));

        Http::assertNothingSent();
        $this->assertSame('approved', $refund->fresh()->status);
    }

    /**
     * @return array{0: User, 1: TicketRefund}
     */
    private function approvedWebRefund(): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => 'Mercado SAC', 'commercial_name' => 'Mercado SAC',
            'ruc' => '20671000822', 'email' => '20671000822@test.pe', 'status' => 'active', 'modality' => 'fluvial', 'commission_rate' => 8,
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);
        $origin = Port::query()->create(['name' => 'Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => 'Nave Mercado', 'registration_number' => 'MAT-MP02',
            'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'MP-2',
        ]);
        RouteDeparture::query()->create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(),
            'fare' => 80, 'status' => 'scheduled', 'is_published' => true,
        ]);
        $reservation = Reservation::query()->create([
            'route_departure_id' => RouteDeparture::query()->firstOrFail()->id, 'code' => 'NY-MPREEM', 'contact_name' => 'Ana Web',
            'contact_email' => 'ana.mp@viajero.test', 'contact_phone' => '999000111',
            'base_amount' => '80.00', 'discount_amount' => '0.00', 'service_fee_amount' => '6.40', 'total_amount' => '86.40',
            'status' => 'confirmed', 'sales_channel' => 'web', 'payment_method' => 'card', 'paid_at' => now(),
        ]);
        Payment::query()->create([
            'reservation_id' => $reservation->id, 'purpose' => 'ticket', 'method' => 'card', 'provider' => 'mercadopago',
            'provider_reference' => 'mp_1001', 'amount' => '86.40', 'commission_rate' => '8.00',
            'commission_amount' => '6.40', 'operator_net' => '80.00', 'commission_status' => 'pending',
            'status' => 'confirmed', 'paid_at' => now(), 'provider_payload' => ['commission_model' => 'passenger_fee'],
        ]);
        $reservation->load('departure.transportRoute', 'payments', 'seats.ticket');
        $call = function (User $user, string $method, TicketRefund $refund) use ($reservation): void {
            $request = Request::create('/', 'POST');
            $request->setUserResolver(fn (): User => $user);
            if ($method === 'request') {
                app(TicketRefunds::class)->request($request, $reservation, 'departure_cancelled', RefundRule::COMPANY_CANCEL, null, 'La empresa canceló la salida.');
            } else {
                app(TicketRefunds::class)->approve($request, $refund, null);
            }
        };
        $this->actingAs($admin);
        $call($admin, 'request', new TicketRefund);
        $refund = TicketRefund::query()->firstOrFail();
        $call($admin, 'approve', $refund);

        return [$admin, $refund->fresh()];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super'])->id);

        return $user;
    }
}
