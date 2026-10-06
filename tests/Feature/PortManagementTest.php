<?php

namespace Tests\Feature;

use App\Models\GeographicDepartment;
use App\Models\GeographicDistrict;
use App\Models\GeographicProvince;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));

        return $admin;
    }

    private function location(): array
    {
        $department = GeographicDepartment::where('code', '16')->firstOrFail();
        $province = GeographicProvince::where('code', '1601')->firstOrFail();
        $district = GeographicDistrict::where('code', '160101')->firstOrFail();

        return [$department, $province, $district];
    }

    public function test_admin_can_use_dependent_location_comboboxes_and_manage_unused_port(): void
    {
        [$department, $province, $district] = $this->location();
        $port = Port::create(['department_id' => $department->id, 'province_id' => $province->id, 'district_id' => $district->id, 'name' => 'Puerto Inicial', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.ports.create'))->assertOk()->assertSee('Departamento')->assertSee('Distrito')->assertSee('Maynas');
        $this->actingAs($admin)->get(route('admin.locations.provinces', $department))->assertOk()->assertJsonFragment(['name' => 'Maynas']);
        $this->actingAs($admin)->get(route('admin.locations.districts', $province))->assertOk()->assertJsonFragment(['name' => 'Iquitos']);
        $this->actingAs($admin)->get(route('admin.ports.index'))->assertOk()->assertSee('Puertos de embarque')->assertSee('Editar');
        $this->actingAs($admin)->patch(route('admin.ports.update', $port), ['department_id' => $department->id, 'province_id' => $province->id, 'district_id' => $district->id, 'name' => 'Puerto Actualizado', 'modality' => 'fluvial', 'port_type' => 'embarcadero', 'river' => 'Amazonas', 'operator_name' => 'Operador de prueba', 'contact_phone' => '999999999'])->assertRedirect(route('admin.ports.index'));
        $this->assertDatabaseHas('ports', ['id' => $port->id, 'name' => 'Puerto Actualizado', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $this->actingAs($admin)->delete(route('admin.ports.destroy', $port))->assertRedirect(route('admin.ports.index'));
        $this->assertDatabaseMissing('ports', ['id' => $port->id]);
    }

    public function test_port_in_use_by_vessel_cannot_be_deleted(): void
    {
        [$department, $province, $district] = $this->location();
        $port = Port::create(['department_id' => $department->id, 'province_id' => $province->id, 'district_id' => $district->id, 'name' => 'Puerto Protegido', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Operador SAC', 'ruc' => '20666666666', 'email' => 'operador@test.pe', 'status' => 'active']);
        Vessel::create(['organization_id' => $company->id, 'base_port_id' => $port->id, 'name' => 'Nave Protegida', 'registration_number' => 'PA-9900', 'vessel_type' => 'lancha', 'seat_capacity' => 10]);
        $this->actingAs($this->admin())->from(route('admin.ports.index'))->delete(route('admin.ports.destroy', $port))->assertRedirect(route('admin.ports.index'))->assertSessionHas('error');
        $this->assertDatabaseHas('ports', ['id' => $port->id]);
    }
}
