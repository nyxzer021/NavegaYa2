<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\GeographicDepartment;
use App\Models\GeographicDistrict;
use App\Models\GeographicProvince;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyBasePortTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_only_choose_an_active_catalog_port_for_its_modality(): void
    {
        [$admin, $organization] = $this->company();
        $river = Port::create(['name' => 'Puerto Nanay', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'port_type' => 'muelle', 'is_active' => true]);
        $inactive = Port::create(['name' => 'Muelle cerrado', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'port_type' => 'muelle', 'is_active' => false]);
        $airfield = Port::create(['name' => 'Aeródromo', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'aereo', 'port_type' => 'aerodromo', 'is_active' => true]);

        $this->actingAs($admin)->get(route('company.details.index'))
            ->assertOk()
            ->assertSee('Puerto Nanay')
            ->assertSee('¿No está tu puerto? Solicítalo a NavegaYA')
            ->assertDontSee('Aeródromo')
            ->assertDontSee('Muelle cerrado');

        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()
            ->assertSee('Puerto base por definir');
        $this->get(route('companies.show', $organization))
            ->assertOk()
            ->assertDontSee('Puerto base');

        $payload = $this->details($organization);
        $this->actingAs($admin)->patch(route('company.details.update'), $payload + ['base_port_id' => 999999])
            ->assertSessionHasErrors('base_port_id');
        $this->actingAs($admin)->patch(route('company.details.update'), $payload + ['base_port_id' => $inactive->id])
            ->assertSessionHasErrors('base_port_id');
        $this->actingAs($admin)->patch(route('company.details.update'), $payload + ['base_port_id' => $airfield->id])
            ->assertSessionHasErrors('base_port_id');
        $this->assertNull($organization->fresh()->base_port_id);

        $this->actingAs($admin)->patch(route('company.details.update'), $payload + ['base_port_id' => $river->id])
            ->assertRedirect();
        $this->assertSame($river->id, $organization->fresh()->base_port_id);
    }

    public function test_super_admin_assignment_is_visible_in_the_company_panel(): void
    {
        [$admin, $organization] = $this->company();
        $port = Port::create(['name' => 'Puerto Asignado', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $super = User::factory()->create();
        $super->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador'])->id);

        $this->actingAs($super)->patch(route('admin.companies.base-port.update', $organization), [
            'base_port_id' => $port->id,
            'reason' => 'La empresa opera desde Nauta',
        ])->assertRedirect();

        $this->assertSame($port->id, $organization->fresh()->base_port_id);
        $this->assertTrue(AuditLog::query()->where('event', 'company_base_port_changed')->where('subject_id', $organization->id)->exists());
        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()
            ->assertSee('Puerto Asignado · Nauta')
            ->assertDontSee('Puerto base por definir');
        $this->actingAs($super)->get(route('admin.ports.index'))
            ->assertOk()
            ->assertSee('Puerto Asignado');

        $this->actingAs($super)->patch(route('admin.ports.update', $port), $this->portForm($port, false))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertTrue($port->fresh()->is_active);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Puerto Base SAC', 'commercial_name' => 'Puerto Base',
            'ruc' => '20999999111', 'email' => 'puerto@test.pe', 'status' => 'active',
            'base_city' => 'Iquitos', 'modality' => 'fluvial', 'address' => 'Malecón 1', 'contact_name' => 'Ana',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Organization $organization): array
    {
        return [
            'legal_name' => $organization->legal_name,
            'commercial_name' => $organization->commercial_name,
            'address' => $organization->address,
            'base_city' => $organization->base_city,
            'contact_name' => $organization->contact_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function portForm(Port $port, bool $active): array
    {
        $department = GeographicDepartment::where('code', '16')->firstOrFail();
        $province = GeographicProvince::where('code', '1601')->firstOrFail();
        $district = GeographicDistrict::where('code', '160101')->firstOrFail();

        return [
            'department_id' => $department->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
            'name' => $port->name,
            'modality' => 'fluvial',
            'port_type' => 'muelle',
            'river' => 'Nanay',
            'is_active' => $active ? '1' : '0',
        ];
    }
}
