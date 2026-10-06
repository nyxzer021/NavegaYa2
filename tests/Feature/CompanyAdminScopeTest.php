<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliated_administrator_only_sees_its_company_fleet(): void
    {
        $first = Organization::create(['type' => 'transport_company', 'legal_name' => 'Fluvial Uno SAC', 'ruc' => '20111111111', 'email' => 'uno@test.pe', 'status' => 'active']);
        $second = Organization::create(['type' => 'transport_company', 'legal_name' => 'Fluvial Dos SAC', 'ruc' => '20222222222', 'email' => 'dos@test.pe', 'status' => 'active']);
        Vessel::create(['organization_id' => $first->id, 'name' => 'Nave Uno', 'registration_number' => 'N-001', 'vessel_type' => 'lancha', 'seat_capacity' => 20]);
        Vessel::create(['organization_id' => $second->id, 'name' => 'Nave Dos', 'registration_number' => 'N-002', 'vessel_type' => 'lancha', 'seat_capacity' => 20]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail(), ['organization_id' => $first->id]);
        $user->organizations()->attach($first->id, ['status' => 'active']);
        $user->permissions()->attach(Permission::where('code', 'fleet')->firstOrFail());

        $this->actingAs($user)->get(route('admin.vessels.index'))->assertOk()->assertSee('Nave Uno')->assertDontSee('Nave Dos');
    }

    public function test_affiliated_administrator_cannot_open_another_company_profile(): void
    {
        $first = Organization::create(['type' => 'transport_company', 'legal_name' => 'Fluvial Uno SAC', 'ruc' => '20111111111', 'email' => 'uno@test.pe', 'status' => 'active']);
        $second = Organization::create(['type' => 'transport_company', 'legal_name' => 'Fluvial Dos SAC', 'ruc' => '20222222222', 'email' => 'dos@test.pe', 'status' => 'active']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail(), ['organization_id' => $first->id]);
        $user->organizations()->attach($first->id, ['status' => 'active']);
        $user->permissions()->attach(Permission::where('code', 'companies')->firstOrFail());

        $this->actingAs($user)->get(route('admin.organization-directory.show', $second))->assertForbidden();
    }
}
