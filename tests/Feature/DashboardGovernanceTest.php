<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_regional_governance_dashboard_and_master_catalog_link(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['code' => 'super_admin', 'name' => 'Super administrador']));
        Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Operador pendiente SAC', 'ruc' => '20999999991',
            'email' => 'pendiente@test.pe', 'contact_name' => 'Ana Pérez', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('GMV Regional Hoy')
            ->assertSee('Comisión Neta NavegaYA')
            ->assertSee('Operador pendiente SAC')
            ->assertSee('Monitoreo de terminales')
            ->assertSee(route('admin.master-routes.index'));
    }
}
