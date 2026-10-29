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

    public function test_super_admin_sees_clear_commercial_dashboard_and_pending_affiliation(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['code' => 'super_admin', 'name' => 'Super administrador']));
        Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Operador pendiente SAC', 'ruc' => '20999999991',
            'email' => 'pendiente@test.pe', 'contact_name' => 'Ana Pérez', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('GMV transporte')
            ->assertSee('Total cobrado por pasajes; no es ingreso neto')
            ->assertSee('Ingreso NavegaYA')
            ->assertSee('Pendientes por atender')
            ->assertDontSee('Control de geografía oficial');
    }

    public function test_affiliation_review_explains_decision_and_can_return_to_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['code' => 'super_admin', 'name' => 'Super administrador']));

        $this->actingAs($admin)->get(route('admin.organizations.index'))
            ->assertOk()
            ->assertSee('Decide qué empresas pueden operar')
            ->assertSee('Bandeja al día')
            ->assertSee(route('admin.dashboard'));
    }
}
