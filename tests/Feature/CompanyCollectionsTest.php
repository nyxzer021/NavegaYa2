<?php

namespace Tests\Feature;

use App\Models\OperatorPayout;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Services\CompanyCollections;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCollectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_statement_keeps_the_fare_and_ignores_dock_and_agency_sales(): void
    {
        [$admin, $organization] = $this->company('Cobradora SAC', '20671000001');
        $departure = $this->departure($organization);
        $this->webSale($departure, 'BOL-NETO', 'Ana Ríos', '100.00', '20.00', '6.40', '80.00', '86.40');
        $this->webSale($departure, 'BOL-MUELLE', 'Luis Muelle', '95.00', '0.00', '0.00', '95.00', '95.00', 'counter');
        $this->webSale($departure, 'BOL-AGENCIA', 'Luz Agencia', '80.00', '0.00', '0.00', '80.00', '80.00', 'agency');
        $legacy = $this->webSale($departure, 'BOL-VIEJO', 'Pedro Viejo', '90.00', '0.00', '7.20', '82.80', '90.00');
        $legacy->update(['provider_payload' => null]);

        $this->actingAs($admin)->get(route('company.collections.index'))
            ->assertOk()
            ->assertSee('Cobros y conciliación')
            ->assertSee('data-count="sales">1<', false)
            ->assertSee('data-count="net">S/ 80.00<', false)
            ->assertSee('S/ 6.40')
            ->assertSee('BOL-NETO')
            ->assertDontSee('BOL-MUELLE')
            ->assertDontSee('BOL-AGENCIA')
            ->assertDontSee('BOL-VIEJO')
            ->assertDontSee('payout.recorded');

        $csv = $this->actingAs($admin)->get(route('company.collections.export'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('BOL-NETO', $body);
        $this->assertStringNotContainsString('BOL-MUELLE', $body);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $body);
    }

    public function test_three_web_sales_a_refund_and_a_partial_payout_leave_sixty(): void
    {
        [$admin, $organization] = $this->company('Caso SAC', '20671000003');
        $departure = $this->departure($organization);
        $first = $this->webSale($departure, 'BOL-A', 'Pasajero A', '80.00', '0.00', '6.40', '80.00', '86.40', 'web', now()->subMinutes(3));
        $this->webSale($departure, 'BOL-B', 'Pasajero B', '80.00', '0.00', '6.40', '80.00', '86.40', 'web', now()->subMinutes(2));
        $this->webSale($departure, 'BOL-C', '=HYPERLINK("x")', '80.00', '0.00', '6.40', '80.00', '86.40', 'web', now()->subMinute());
        $super = $this->superAdmin();
        $this->refund($admin, $first);

        $this->actingAs($admin)->get(route('company.collections.index'))
            ->assertSee('data-count="pending">S/ 160.00<', false)
            ->assertSee('data-count="refunds">S/ 80.00<', false);

        $this->actingAs($super)->post(route('admin.commissions.payouts.store', $organization), [
            'paid_on' => now()->timezone('America/Lima')->toDateString(),
            'amount' => '100.00',
            'method' => 'Banco de la Selva',
            'operation_number' => 'OP-100',
            'note' => 'Pago parcial',
        ])->assertRedirect();
        $this->actingAs($super)->post(route('admin.commissions.payouts.store', $organization), [
            'paid_on' => now()->timezone('America/Lima')->toDateString(),
            'amount' => '500.00',
            'method' => 'Banco de la Selva',
            'operation_number' => 'OP-DEMAS',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($admin)->get(route('company.collections.index'))
            ->assertOk()
            ->assertSee('data-count="sales">3<', false)
            ->assertSee('data-count="net">S/ 240.00<', false)
            ->assertSee('data-count="paid">S/ 100.00<', false)
            ->assertSee('data-count="pending">S/ 60.00<', false)
            ->assertSee('OP-100')
            ->assertSee('BOL-B')
            ->assertDontSee('Registrar pago');

        $csv = $this->actingAs($admin)->get(route('company.collections.export'))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('80.00', $csv);

        $payoutId = OperatorPayout::query()->where('operation_number', 'OP-100')->value('id');
        $this->actingAs($super)->post(route('admin.commissions.payouts.void', $payoutId), [
            'reason' => 'El número de operación estaba mal',
        ])->assertRedirect();
        $this->actingAs($admin)->get(route('company.collections.index'))
            ->assertSee('data-count="pending">S/ 160.00<', false)
            ->assertSee('data-count="paid">S/ 0.00<', false)
            ->assertSee('Anulado');
    }

    public function test_a_refund_after_settlement_is_a_credit_in_the_next_period(): void
    {
        [$admin, $organization] = $this->company('Favor SAC', '20671000004');
        $departure = $this->departure($organization);
        $sale = $this->webSale($departure, 'BOL-OCT', 'Octubre', '80.00', '0.00', '6.40', '80.00', '86.40', 'web', Carbon::parse('2026-10-15 12:00:00', 'America/Lima'));
        $super = $this->superAdmin();
        $this->actingAs($super)->post(route('admin.commissions.payouts.store', $organization), [
            'paid_on' => '2026-10-20',
            'amount' => '80.00',
            'method' => 'Transferencia',
            'operation_number' => 'OP-OCT',
        ])->assertRedirect();

        $this->travelTo(Carbon::parse('2026-11-10 15:00:00', 'America/Lima'));
        $this->refund($admin, $sale);

        $this->actingAs($admin)->get(route('company.collections.index', ['vista' => 'mes', 'fecha' => '2026-10-15']))
            ->assertOk()
            ->assertSee('data-count="sales">1<', false)
            ->assertSee('data-count="paid">S/ 80.00<', false)
            ->assertSee('data-count="refunds">S/ 0.00<', false)
            ->assertSee('data-count="pending">S/ 0.00<', false);

        $this->actingAs($admin)->get(route('company.collections.index', ['vista' => 'mes', 'fecha' => '2026-11-10']))
            ->assertOk()
            ->assertSee('data-count="sales">0<', false)
            ->assertSee('data-count="refunds">S/ 80.00<', false)
            ->assertSee('data-count="pending">S/ 80.00<', false)
            ->assertSee('A favor de NavegaYA')
            ->assertSee('BOL-OCT');
    }

    public function test_another_company_and_the_seller_cannot_see_the_statement(): void
    {
        [$admin, $organization] = $this->company('Visible SAC', '20671000005');
        [$other] = $this->company('Ajena SAC', '20671000006');
        $departure = $this->departure($organization);
        $this->webSale($departure, 'BOL-VISIBLE', 'Solo Visible', '80.00', '0.00', '6.40', '80.00', '86.40');
        $seller = $this->member($organization, 'company_counter');
        $operator = $this->member($organization, 'company_operator');
        $finance = $this->member($organization, 'company_finance');

        $this->actingAs($other)->get(route('company.collections.index'))
            ->assertOk()
            ->assertDontSee('BOL-VISIBLE')
            ->assertSee('data-count="sales">0<', false);
        $this->actingAs($finance)->get(route('company.collections.index'))->assertOk()->assertSee('BOL-VISIBLE');
        $this->actingAs($seller)->get(route('company.collections.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('company.collections.export'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.collections.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.commissions.payouts.store', $organization), [
            'paid_on' => now()->toDateString(),
            'amount' => '10.00',
            'method' => 'Banco',
            'operation_number' => 'OP-NO',
        ])->assertForbidden();
    }

    private function refund(User $actor, Payment $payment): void
    {
        $this->actingAs($actor);
        app(CompanyCollections::class)->recordRefund(request(), $payment, '80.00', 'El pasajero desistió');
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

    private function superAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Super Cobros']);
        $user->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super'])->id);

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
            'organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'COB-'.$organization->id,
        ]);

        return RouteDeparture::query()->create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(),
            'fare' => 80, 'status' => 'scheduled', 'is_published' => true,
        ]);
    }

    private function webSale(RouteDeparture $departure, string $code, string $passenger, string $base, string $discount, string $fee, string $net, string $total, string $channel = 'web', ?Carbon $paidAt = null): Payment
    {
        $paidAt ??= now();
        $reservation = Reservation::query()->create([
            'route_departure_id' => $departure->id,
            'code' => $code,
            'contact_name' => $passenger,
            'contact_email' => strtolower($code).'@viajero.test',
            'contact_phone' => '999000111',
            'base_amount' => $base,
            'discount_amount' => $discount,
            'service_fee_amount' => $fee,
            'total_amount' => $total,
            'status' => 'confirmed',
            'sales_channel' => $channel,
            'paid_at' => $paidAt,
        ]);

        return Payment::query()->create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => 'card',
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
