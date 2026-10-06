<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCommissionArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_commissions_without_operator_payout_controls(): void
    {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador']);
        $admin->roles()->attach($role);

        $this->actingAs($admin)
            ->get(route('admin.commissions.index'))
            ->assertOk()
            ->assertSee('Comisiones y Facturación')
            ->assertSee('Sin fondos de operadores en custodia')
            ->assertDontSee('Registrar transferencia');
    }

    public function test_company_sees_its_contractual_commission_without_bank_settlement_form(): void
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Operador Marketplace SAC',
            'ruc' => '20999999991',
            'email' => 'operador@example.test',
            'status' => 'active',
            'commission_rate' => 7.5,
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador de empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        $this->actingAs($admin)
            ->get(route('company.commissions.index'))
            ->assertOk()
            ->assertSee('Comisión vigente: 7.50%')
            ->assertSee('Sin saldos por transferir')
            ->assertDontSee('Cuenta bancaria')
            ->assertDontSee('CCI');
    }
}
