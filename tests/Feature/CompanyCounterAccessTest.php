<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCounterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_creates_counter_scoped_to_its_organization(): void
    {
        [$admin, $organization] = $this->companyAdmin();
        Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Counter']);

        $this->actingAs($admin)->post(route('company.staff.store'), [
            'name' => 'Counter Puerto', 'email' => 'counter@example.test', 'document_number' => '12345678',
            'phone' => '999888777', 'password' => 'secreto123', 'password_confirmation' => 'secreto123',
        ])->assertRedirect();

        $counter = User::where('email', 'counter@example.test')->firstOrFail();
        $this->assertDatabaseHas('organization_user', ['organization_id' => $organization->id, 'user_id' => $counter->id, 'status' => 'active']);
        $this->assertDatabaseHas('role_user', ['user_id' => $counter->id, 'organization_id' => $organization->id]);
        $this->actingAs($counter)->get(route('dashboard'))->assertRedirect(route('company.counter.index'));
        $this->actingAs($counter)->get(route('company.counter.index'))->assertOk()->assertSee('Portal Counter')->assertDontSee('Recaudación hoy');
        $this->actingAs($counter)->get(route('company.departures.index'))->assertOk()->assertSee('Mis Salidas y Despacho');
        $this->actingAs($counter)->get(route('company.fleet.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.staff.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.settlements.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.finance.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.dashboard'))->assertForbidden();
        $this->actingAs($counter)->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_super_admin_cannot_enter_dock_boarding_tools(): void
    {
        $super = User::factory()->create();
        $super->roles()->attach(Role::create(['code' => 'super_admin', 'name' => 'Super administrador']));
        $this->actingAs($super)->get(route('admin.boarding.index'))->assertForbidden();
    }

    private function companyAdmin(): array
    {
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => 'Operador Loreto SAC', 'ruc' => '20111222333', 'email' => 'operador@example.test', 'status' => 'active']);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['code' => 'company_admin', 'name' => 'Administrador empresa']), ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }
}
