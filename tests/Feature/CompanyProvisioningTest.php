<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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

    public function test_public_channel_creates_a_pending_application_without_an_account(): void
    {
        Storage::fake('local');
        Notification::fake();
        $response = $this->post(route('company.registration.store'), [
            'legal_name' => 'Ríos del Oriente SAC',
            'commercial_name' => 'Oriente Fluvial',
            'ruc' => '20888888888',
            'email' => 'duena@oriente.test',
            'contact_name' => 'María Operadora',
            'phone' => '965111222',
            'address' => 'Puerto de Iquitos',
            'modality' => 'fluvial',
            'base_city' => 'Iquitos',
            'terms' => '1',
            'ruc_document' => UploadedFile::fake()->create('ruc.pdf', 50, 'application/pdf'),
            'representative_document' => UploadedFile::fake()->image('dni.jpg'),
            'operating_permit' => UploadedFile::fake()->create('permiso.pdf', 50, 'application/pdf'),
        ]);

        $response->assertOk()->assertSee('Solicitud registrada');
        $this->assertGuest();
        $organization = Organization::where('ruc', '20888888888')->firstOrFail();
        $this->assertSame('pending', $organization->status);
        $this->assertCount(3, $organization->documents);
        $this->assertDatabaseMissing('users', ['email' => 'duena@oriente.test']);
        Notification::assertCount(1);
    }

    public function test_super_admin_has_no_manual_company_creation_route(): void
    {
        $superAdmin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']);
        $superAdmin->roles()->attach($role);

        $this->actingAs($superAdmin);
        $this->assertFalse(Route::has('admin.companies.create'));
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
