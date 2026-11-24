<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_sees_only_the_last_four_characters_of_each_key(): void
    {
        config([
            'services.culqi.enabled' => true,
            'services.culqi.environment' => 'test',
            'services.culqi.api_url' => 'https://api.culqi.com/v2',
            'services.culqi.public_key' => 'pk_demo_ABCD9ZQ4',
            'services.culqi.secret_key' => 'secret_demo_77LM',
            'services.culqi.webhook_secret' => 'whsec_demo_3KX8',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.payment-connections.index'))
            ->assertOk()
            ->assertSee('Conexiones de pago')
            ->assertSee('Activo')
            ->assertSee('Prueba')
            ->assertSee('Presente ····9ZQ4')
            ->assertSee('Presente ····77LM')
            ->assertSee('Presente ····3KX8')
            ->assertSee(route('webhooks.culqi'))
            ->assertSee('Aún no llega ninguno.')
            ->assertSee('Mercado Pago')
            ->assertSee('Apagado')
            ->assertSee(route('webhooks.mercadopago'))
            ->assertDontSee('pk_demo_ABCD9ZQ4')
            ->assertDontSee('secret_demo_77LM')
            ->assertDontSee('whsec_demo_3KX8');
    }

    public function test_a_short_key_is_marked_present_without_its_characters(): void
    {
        config([
            'services.culqi.enabled' => false,
            'services.culqi.environment' => 'production',
            'services.culqi.public_key' => 'qx7',
            'services.culqi.secret_key' => '',
            'services.culqi.webhook_secret' => '',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.payment-connections.index'))
            ->assertOk()
            ->assertSee('Apagado')
            ->assertSee('Producción')
            ->assertSee('Presente')
            ->assertDontSee('qx7');
    }

    public function test_probe_does_not_call_culqi_when_it_is_off_and_reads_charges_when_it_is_on(): void
    {
        Http::fake();
        config([
            'services.culqi.enabled' => false,
            'services.culqi.secret_key' => 'secret_demo_77LM',
            'services.culqi.api_url' => 'https://api.culqi.com/v2',
        ]);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.payment-connections.probe'), ['provider' => 'culqi'])
            ->assertRedirect()
            ->assertSessionHas('probe_error', 'Culqi está apagado o le falta la clave secreta. No se llamó al proveedor.');
        Http::assertNothingSent();

        $this->post(route('admin.payment-connections.probe'), ['provider' => 'mercadopago'])
            ->assertRedirect()
            ->assertSessionHas('probe_error', 'Mercado Pago está apagado o le falta el token. No se llamó al proveedor.');
        Http::assertNothingSent();

        config([
            'services.culqi.enabled' => true,
            'services.culqi.secret_key' => 'secret_demo_77LM',
        ]);
        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        $this->from(route('admin.payment-connections.index'))
            ->followingRedirects()
            ->post(route('admin.payment-connections.probe'), ['provider' => 'culqi'])
            ->assertOk()
            ->assertSee('La conexión respondió. No se creó ningún cobro.')
            ->assertDontSee('secret_demo_77LM');
        Http::assertSent(fn ($request): bool => $request->method() === 'GET' && str_contains($request->url(), '/charges?limit=1'));
        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
    }

    public function test_a_failed_probe_shows_the_provider_message_without_the_key(): void
    {
        config([
            'services.culqi.enabled' => true,
            'services.culqi.secret_key' => 'secret_demo_77LM',
            'services.culqi.api_url' => 'https://api.culqi.com/v2',
        ]);
        Http::fake([
            '*' => Http::response(['user_message' => 'El proveedor rechazó la lectura.'], 401),
        ]);

        $this->actingAs($this->superAdmin())
            ->from(route('admin.payment-connections.index'))
            ->followingRedirects()
            ->post(route('admin.payment-connections.probe'), ['provider' => 'culqi'])
            ->assertOk()
            ->assertSee('El proveedor rechazó la lectura.')
            ->assertDontSee('secret_demo_77LM');
    }

    public function test_the_last_webhook_date_is_kept_even_when_the_signature_fails(): void
    {
        Carbon::setTestNow('2026-11-23 15:30:00');

        $this->postJson(route('webhooks.culqi'), ['type' => 'ping'])->assertUnauthorized();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.payment-connections.index'))
            ->assertOk()
            ->assertSee(now()->timezone('America/Lima')->format('Y-m-d H:i'));
    }

    public function test_company_sees_only_its_agreement_and_a_masked_account(): void
    {
        [$admin, $organization] = $this->company('20999999111', '8.00', '1111222233334444', '00212345678901234561');
        $this->company('20999999222', '3.25', '9999000011112222', '00998877665544332211');

        $this->actingAs($admin)
            ->get(route('company.online-payments.index'))
            ->assertOk()
            ->assertSee('Pagos en línea')
            ->assertSee('8.00 %')
            ->assertSee('Presente ····4444')
            ->assertSee('Presente ····4561')
            ->assertSee('Guardar cuenta')
            ->assertSee('Cobros y conciliación')
            ->assertDontSee('3.25')
            ->assertDontSee('1111222233334444')
            ->assertDontSee('9999000011112222')
            ->assertDontSee('00212345678901234561')
            ->assertDontSee('00998877665544332211');
    }

    public function test_seller_and_operator_cannot_open_or_save_the_account(): void
    {
        [, $organization] = $this->company('20999999333', '8.00', '1111222233334444', null);

        foreach (['company_counter', 'company_operator'] as $code) {
            $user = User::factory()->create();
            $user->roles()->attach(Role::firstOrCreate(['code' => $code], ['name' => $code])->id, ['organization_id' => $organization->id]);
            $user->organizations()->attach($organization->id, ['status' => 'active']);

            $this->actingAs($user)->get(route('company.online-payments.index'))->assertForbidden();
            $this->actingAs($user)->patch(route('company.finance.bank'), [
                'bank_name' => 'Banco Ajeno',
                'bank_account' => '5555666677774419',
            ])->assertForbidden();
        }

        $this->assertSame('1111222233334444', $organization->fresh()->bank_account);
    }

    public function test_saving_the_account_audits_only_the_bank_name(): void
    {
        [$admin, $organization] = $this->company('20999999444', '8.00', '1111222233334444', '00212345678901234561');

        $this->actingAs($admin)
            ->from(route('company.online-payments.index'))
            ->followingRedirects()
            ->patch(route('company.finance.bank'), [
                'bank_name' => 'Banco de la Selva',
                'bank_account' => '5555666677774419',
                'bank_cci' => '',
            ])
            ->assertOk()
            ->assertSee('Cuenta bancaria actualizada de forma segura.')
            ->assertSee('Presente ····4419')
            ->assertSee('Presente ····4561')
            ->assertDontSee('5555666677774419')
            ->assertDontSee('00212345678901234561');

        $organization->refresh();
        $this->assertSame('5555666677774419', $organization->bank_account);
        $this->assertSame('00212345678901234561', $organization->bank_cci);

        $log = AuditLog::query()->where('event', 'bank.updated')->first();
        $this->assertNotNull($log);
        $this->assertSame(['bank' => 'Banco de la Selva'], $log->changes);
        $stored = json_encode($log->getAttributes());
        $this->assertStringNotContainsString('5555666677774419', (string) $stored);
        $this->assertStringNotContainsString('00212345678901234561', (string) $stored);
    }

    public function test_a_company_user_cannot_open_payment_connections(): void
    {
        [$admin] = $this->company('20999999555', '8.00', null, null);

        $this->actingAs($admin)->get(route('admin.payment-connections.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.payment-connections.probe'), ['provider' => 'culqi'])->assertForbidden();
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador'])->id);

        return $admin;
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $ruc, string $rate, ?string $account, ?string $cci): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Operador '.$ruc,
            'ruc' => $ruc,
            'email' => $ruc.'@example.test',
            'status' => 'active',
            'commission_rate' => $rate,
            'bank_name' => 'Banco Anterior',
            'bank_account' => $account,
            'bank_cci' => $cci,
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }
}
