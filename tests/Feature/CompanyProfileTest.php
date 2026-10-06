<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_update_only_its_own_public_profile(): void
    {
        $role = Role::create(['code' => 'company_admin', 'name' => 'Administrador de empresa']);
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => 'Río Verde SAC', 'ruc' => '20123456789', 'email' => 'rio@example.test', 'status' => 'active']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization->id, ['status' => 'active']);
        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);

        $this->actingAs($user)->patch(route('company.dashboard.profile'), [
            'commercial_name' => 'Río Verde', 'whatsapp' => '51999111222',
            'phone' => '999111222', 'website' => 'https://rioverde.test',
            'address' => 'Iquitos', 'public_description' => 'Transporte seguro por el río.',
        ])->assertRedirect();

        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'commercial_name' => 'Río Verde', 'whatsapp' => '51999111222']);
    }
}
