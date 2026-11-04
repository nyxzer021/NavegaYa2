<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPortalMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_the_operator_menu_and_preparation_pages(): void
    {
        [$admin] = $this->companyAdmin();

        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()
            ->assertSee('Inicio')
            ->assertSee('Cobros y conciliación')
            ->assertSee('Reembolsos')
            ->assertSee('Pagos en línea')
            ->assertSee('Requiere implementación')
            ->assertSee('Cargo de servicio NavegaYA')
            ->assertDontSee('Boletos vendidos');

        $this->actingAs($admin)->get(route('company.collections.index'))
            ->assertOk()
            ->assertSee('Cobros y conciliación')
            ->assertSee('no mostramos un conteo');

        $this->actingAs($admin)->get(route('company.refunds.index'))
            ->assertOk()
            ->assertSee('Reembolsos');

        $this->actingAs($admin)->get(route('company.online-payments.index'))
            ->assertOk()
            ->assertSee('Pagos en línea')
            ->assertSee('Mercado Pago');
    }

    public function test_counter_cannot_open_finance_preparation_pages(): void
    {
        [, $organization] = $this->companyAdmin();
        $counter = User::factory()->create();
        $counter->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Counter'])->id, ['organization_id' => $organization->id]);
        $counter->organizations()->attach($organization->id, ['status' => 'active']);

        $this->actingAs($counter)->get(route('company.collections.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.refunds.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.online-payments.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.counter.index'))
            ->assertOk()
            ->assertSee('Embarque')
            ->assertDontSee('Cobros y conciliación');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function companyAdmin(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Operador Menú',
            'commercial_name' => 'Menú Fast',
            'ruc' => '20999999001',
            'email' => 'menu@test.pe',
            'status' => 'active',
            'base_city' => 'Iquitos',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }
}
