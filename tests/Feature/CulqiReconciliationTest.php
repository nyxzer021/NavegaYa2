<?php

namespace Tests\Feature;

use App\Models\CulqiSettlementLine;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CulqiReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_matching_file_has_no_differences_and_shows_the_net_gain(): void
    {
        $this->payment('chr_match', '86.40', '6.40', '80.00', '2026-11-18 15:00:00');
        $csv = "id,fecha,tipo,monto,comision,neto\nchr_match,18/11/2026,cargo,86.40,2.59,83.81\n";

        $this->actingAs($this->superAdmin())
            ->from(route('admin.reconciliation.index', ['mes' => '2026-11']))
            ->post(route('admin.reconciliation.import'), ['archivo' => $this->file($csv)])
            ->assertRedirect();

        $this->get(route('admin.reconciliation.index', ['mes' => '2026-11']))
            ->assertOk()
            ->assertSee('Cobros y conciliación')
            ->assertSee('Sin diferencias')
            ->assertSee('86.40')
            ->assertSee('6.40')
            ->assertSee('2.59')
            ->assertSee('80.00')
            ->assertSee('3.81')
            ->assertDontSee('Cargo en Culqi sin pago en NavegaYA');
    }

    public function test_each_difference_is_marked_and_the_same_file_is_not_imported_twice(): void
    {
        $this->payment('chr_match', '86.40', '6.40', '80.00', '2026-11-18 15:00:00');
        $this->payment('chr_local', '55.00', '4.40', '50.60', '2026-11-19 15:00:00');
        $this->payment('chr_distinto', '90.00', '7.20', '82.80', '2026-11-19 16:00:00');
        $returned = $this->payment('chr_dev', '86.40', '6.40', '80.00', '2026-11-10 15:00:00');
        $this->payment('chr_octubre', '10.00', '0.80', '9.20', '2026-11-01 03:00:00');
        TicketRefund::query()->create([
            'organization_id' => $this->organizationId($returned),
            'reservation_id' => $returned->reservation_id,
            'payment_id' => $returned->id,
            'source' => 'web',
            'cause' => 'passenger',
            'status' => 'paid',
            'fare_amount' => '80.00',
            'fee_amount' => '6.40',
            'amount' => '86.40',
            'explanation' => 'Devolución del pasaje y del cargo.',
            'provider_refund_id' => 'ref_interno',
            'paid_at' => '2026-11-21 15:00:00',
        ]);
        $csv = implode("\n", [
            'id,fecha,tipo,monto,comision,neto,tarjeta',
            'chr_match,2026-11-18,cargo,86.40,2.59,83.81,4111111111111111',
            'chr_fuera,2026-11-18,cargo,40.00,1.20,38.80,4111111111111111',
            'chr_distinto,2026-11-19,cargo,100.00,3.00,97.00,',
            'chr_dev,2026-11-10,cargo,86.40,2.59,83.81,',
            'ref_ajeno,2026-11-20,reembolso,10.00,0.00,10.00,',
        ]);

        $admin = $this->superAdmin();
        $this->actingAs($admin)
            ->from(route('admin.reconciliation.index', ['mes' => '2026-11']))
            ->followingRedirects()
            ->post(route('admin.reconciliation.import'), ['archivo' => $this->file($csv)])
            ->assertOk()
            ->assertSee('Se importaron 5 filas.')
            ->assertSee('Cargo en Culqi sin pago en NavegaYA')
            ->assertSee('Pago en NavegaYA sin cargo en Culqi')
            ->assertSee('Montos distintos')
            ->assertSee('Reembolso en Culqi sin registro')
            ->assertSee('Reembolso sin registro en Culqi')
            ->assertSee('317.80')
            ->assertSee('86.40')
            ->assertSee('9.38')
            ->assertSee('24.40')
            ->assertSee('213.40')
            ->assertSee('8.62')
            ->assertDontSee('327.80')
            ->assertDontSee('4111111111111111');

        $this->assertSame(5, CulqiSettlementLine::query()->count());
        $stored = json_encode(DB::table('culqi_settlement_lines')->get()).json_encode(DB::table('audit_logs')->get());
        $this->assertStringNotContainsString('4111111111111111', (string) $stored);

        $this->post(route('admin.reconciliation.import'), ['archivo' => $this->file($csv)])
            ->assertRedirect()
            ->assertSessionHas('status', 'Este archivo ya fue importado. No se duplicó ninguna fila.');
        $this->assertSame(5, CulqiSettlementLine::query()->count());

        $this->from(route('admin.reconciliation.index', ['mes' => '2026-11']))
            ->followingRedirects()
            ->post(route('admin.reconciliation.review'), [
                'mes' => '2026-11',
                'difference_key' => 'culqi_charge:chr_fuera',
                'note' => '=cmd',
            ])
            ->assertOk()
            ->assertSee('Revisada')
            ->assertSee('=cmd');

        $exported = $this->get(route('admin.reconciliation.export', ['mes' => '2026-11']))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString("'=cmd", $exported);
        $this->assertStringContainsString('8.62', $exported);
        $this->assertStringNotContainsString('4111111111111111', $exported);
    }

    public function test_invalid_columns_are_rejected(): void
    {
        $csv = "id,fecha,tipo\nchr_match,2026-11-18,cargo\n";

        $this->actingAs($this->superAdmin())
            ->from(route('admin.reconciliation.index', ['mes' => '2026-11']))
            ->post(route('admin.reconciliation.import'), ['archivo' => $this->file($csv)])
            ->assertRedirect()
            ->assertSessionHasErrors('archivo');

        $this->assertSame(0, CulqiSettlementLine::query()->count());
    }

    public function test_fetching_charges_keeps_no_card_data_and_does_not_duplicate(): void
    {
        config([
            'services.culqi.enabled' => true,
            'services.culqi.secret_key' => 'secret_demo_77LM',
            'services.culqi.api_url' => 'https://api.culqi.com/v2',
        ]);
        $created = Carbon::parse('2026-11-18 15:00:00', 'America/Lima')->timestamp;
        Http::fake([
            'https://api.culqi.com/v2/charges*' => Http::response([
                'data' => [[
                    'id' => 'chr_api_1',
                    'amount' => 8640,
                    'creation_date' => $created,
                    'total_fee' => 259,
                    'email' => 'ana@viajero.test',
                    'source' => ['card_number' => '4111111111111111', 'cvv' => '123'],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->superAdmin())
            ->from(route('admin.reconciliation.index', ['mes' => '2026-11']))
            ->post(route('admin.reconciliation.fetch'), ['mes' => '2026-11'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Se trajeron 1 cargos. 0 ya estaban.');

        $this->assertSame(1, CulqiSettlementLine::query()->count());
        $stored = json_encode(DB::table('culqi_settlement_lines')->get());
        $this->assertStringNotContainsString('4111111111111111', (string) $stored);
        $this->assertStringNotContainsString('ana@viajero.test', (string) $stored);
        $this->assertStringNotContainsString('secret_demo_77LM', (string) $stored);

        $this->post(route('admin.reconciliation.fetch'), ['mes' => '2026-11'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Se trajeron 0 cargos. 1 ya estaban.');
        $this->assertSame(1, CulqiSettlementLine::query()->count());
        Http::assertSent(fn ($request): bool => $request->method() === 'GET');
    }

    public function test_fetch_does_not_call_culqi_when_it_is_off(): void
    {
        config(['services.culqi.enabled' => false, 'services.culqi.secret_key' => '']);
        Http::fake();

        $this->actingAs($this->superAdmin())
            ->from(route('admin.reconciliation.index', ['mes' => '2026-11']))
            ->post(route('admin.reconciliation.fetch'), ['mes' => '2026-11'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Culqi está apagado o le falta la clave secreta. No se llamó al proveedor.');
        Http::assertNothingSent();
        $this->assertSame(0, CulqiSettlementLine::query()->count());
    }

    public function test_only_the_super_admin_can_reconcile(): void
    {
        $company = User::factory()->create();
        $organization = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Operador', 'ruc' => '20999999771',
            'email' => 'op@example.test', 'status' => 'active',
        ]);
        $company->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Empresa'])->id, ['organization_id' => $organization->id]);
        $platform = User::factory()->create();
        $platform->roles()->attach(Role::firstOrCreate(['code' => 'admin'], ['name' => 'Admin'])->id);

        foreach ([$company, $platform] as $user) {
            $this->actingAs($user)->get(route('admin.reconciliation.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.reconciliation.import'), ['archivo' => $this->file("id,fecha,tipo,monto\n")])->assertForbidden();
            $this->actingAs($user)->post(route('admin.reconciliation.fetch'), ['mes' => '2026-11'])->assertForbidden();
            $this->actingAs($user)->get(route('admin.reconciliation.export', ['mes' => '2026-11']))->assertForbidden();
        }
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super'])->id);

        return $user;
    }

    private function file(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('liquidacion.csv', $contents);
    }

    private function payment(string $reference, string $amount, string $fee, string $net, string $paidAt): Payment
    {
        $reservation = $this->reservation();

        return Payment::query()->create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => 'card',
            'provider' => 'culqi',
            'provider_reference' => $reference,
            'amount' => $amount,
            'commission_rate' => '8.00',
            'commission_amount' => $fee,
            'operator_net' => $net,
            'commission_status' => 'pending',
            'status' => 'confirmed',
            'paid_at' => $paidAt,
            'provider_payload' => ['commission_model' => 'passenger_fee'],
        ]);
    }

    private function organizationId(Payment $payment): int
    {
        return (int) $payment->reservation->departure->transportRoute->organization_id;
    }

    private function reservation(): Reservation
    {
        $organization = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Operador '.fake()->unique()->numerify('####'),
            'ruc' => fake()->unique()->numerify('20#########'), 'email' => fake()->unique()->safeEmail(),
            'status' => 'active', 'modality' => 'fluvial', 'commission_rate' => 8,
        ]);
        $origin = Port::query()->create(['name' => 'Iquitos '.fake()->unique()->numerify('###'), 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Nauta '.fake()->unique()->numerify('###'), 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => 'Nave', 'registration_number' => fake()->unique()->bothify('MAT-####'),
            'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
            'code' => fake()->unique()->bothify('R-####'),
        ]);
        $departure = RouteDeparture::query()->create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(),
            'fare' => 80, 'status' => 'scheduled', 'is_published' => true,
        ]);

        return Reservation::query()->create([
            'route_departure_id' => $departure->id, 'code' => fake()->unique()->bothify('NY-####'),
            'contact_name' => 'Ana Web', 'contact_email' => fake()->unique()->safeEmail(), 'contact_phone' => '999000111',
            'base_amount' => '80.00', 'discount_amount' => '0.00', 'service_fee_amount' => '6.40', 'total_amount' => '86.40',
            'status' => 'confirmed', 'sales_channel' => 'web', 'payment_method' => 'card', 'paid_at' => now(),
        ]);
    }
}
