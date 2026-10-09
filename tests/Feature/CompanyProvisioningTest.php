<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CompanyProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_provisions_pending_company_and_root_admin_atomically(): void
    {
        [$organization, $user] = app(CompanyProvisioningService::class)->provision($this->payload(), false);

        $this->assertSame('pending', $organization->status);
        $this->assertSame('fluvial', $organization->modality);
        $this->assertTrue($user->organizations()->whereKey($organization->id)->exists());
        $this->assertTrue($user->roles()->where('code', 'company_admin')->wherePivot('organization_id', $organization->id)->exists());
        $this->assertSame('pending', $user->organizations()->first()->pivot->status);
    }

    public function test_admin_provisioning_activates_company_and_root_access_immediately(): void
    {
        $payload = $this->payload();
        $payload['ruc'] = '20999999998';
        $payload['admin_email'] = 'admin2@selva.test';
        [$organization, $user] = app(CompanyProvisioningService::class)->provision($payload, true);

        $this->assertSame('active', $organization->status);
        $this->assertNotNull($organization->verified_at);
        $this->assertNotNull($organization->contact_verified_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('active', $user->organizations()->first()->pivot->status);
    }

    public function test_public_channel_logs_in_pending_admin_with_isolated_portal_access(): void
    {
        $response = $this->post(route('company.register.store'), [
            'type' => 'transport_company',
            'legal_name' => 'Ríos del Oriente SAC',
            'commercial_name' => 'Oriente Fluvial',
            'ruc' => '20888888888',
            'email' => 'duena@oriente.test',
            'contact_name' => 'María Operadora',
            'phone' => '965111222',
            'address' => 'Puerto de Iquitos',
            'modality' => 'fluvial',
            'base_city' => 'Iquitos',
            'admin_password' => 'ClaveSegura123',
            'admin_password_confirmation' => 'ClaveSegura123',
        ]);

        $response->assertRedirect(route('company.departures.index'));
        $this->assertAuthenticated();
        $this->get(route('company.departures.index'))->assertOk()->assertSee('pendiente de verificación');
        $this->get(route('company.fleet.index'))->assertOk();
        $this->get(route('company.sales.index'))->assertOk();
        $this->get(route('company.pos.index'))->assertOk();
    }

    public function test_super_admin_is_redirected_to_the_self_service_affiliation_channel(): void
    {
        $superAdmin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']);
        $superAdmin->roles()->attach($role);

        $this->actingAs($superAdmin)->get(route('admin.companies.create'))
            ->assertRedirect(route('company.registration'));

        $this->assertFalse(Route::has('admin.companies.store'));
        $this->assertDatabaseMissing('organizations', ['ruc' => '20777777776']);
    }

    private function payload(): array
    {
        return [
            'company_name' => 'Transportes Selva SAC',
            'commercial_name' => 'Selva Express',
            'ruc' => '20999999999',
            'phone' => '965123456',
            'modality' => 'fluvial',
            'base_city' => 'Iquitos',
            'commission_rate' => 8,
            'admin_name' => 'Ana Operadora',
            'admin_email' => 'admin@selva.test',
            'admin_password' => 'ClaveSegura123',
        ];
    }
}
