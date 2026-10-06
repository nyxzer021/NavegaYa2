<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_company_contact_can_activate_account(): void
    {
        $role = Role::create(['code' => 'company_admin', 'name' => 'Administrador empresa']);
        $org = Organization::create(['type' => 'transport_company', 'legal_name' => 'Prueba SAC', 'ruc' => '20123456789', 'email' => 'empresa@example.test', 'phone' => '999999999', 'address' => 'Iquitos', 'status' => 'active', 'contact_verified_at' => now()]);
        $inv = OrganizationInvitation::create(['organization_id' => $org->id, 'email' => $org->email, 'token' => 'test-token', 'expires_at' => now()->addDay()]);
        $this->post('/activar-empresa/test-token', ['name' => 'Responsable', 'password' => 'secreto123', 'password_confirmation' => 'secreto123'])->assertRedirect(route('company.dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'empresa@example.test']);
        $this->assertDatabaseHas('role_user', ['role_id' => $role->id, 'organization_id' => $org->id]);
        $this->assertDatabaseHas('organization_user', ['organization_id' => $org->id, 'status' => 'active']);
        $this->assertNotNull($inv->fresh()->accepted_at);
    }
}
