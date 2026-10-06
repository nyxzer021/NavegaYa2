<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VesselManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_update_the_vessel_technical_record(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['code' => 'super_admin', 'name' => 'Super administrador']));
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Navegación Test SAC', 'ruc' => '20123456780', 'email' => 'operador@test.pe', 'status' => 'active']);
        $port = Port::create(['name' => 'Puerto de prueba', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Río Claro', 'registration_number' => 'PA-1000', 'vessel_type' => 'lancha', 'seat_capacity' => 20]);

        $this->actingAs($admin)->get(route('admin.vessels.edit', $vessel))
            ->assertOk()
            ->assertSee('Ficha de la embarcación')
            ->assertSee('Matrícula / registro oficial');

        $this->actingAs($admin)->patch(route('admin.vessels.update', $vessel), [
            'organization_id' => $company->id, 'base_port_id' => $port->id, 'name' => 'Río Claro II',
            'registration_number' => 'PA-1000', 'vessel_type' => 'lancha', 'seat_capacity' => 24,
            'crew_capacity' => 4, 'gross_tonnage' => '32.50', 'length_m' => '18.40', 'beam_m' => '4.80',
            'draft_m' => '1.20', 'hull_material' => 'Aluminio', 'engine_description' => '2 motores 250 HP',
            'manufacture_year' => 2024, 'insurance_policy' => 'POL-100', 'insurance_expires_at' => '2027-01-31',
            'inspection_expires_at' => '2027-02-28',
        ])->assertRedirect(route('admin.vessels.edit', $vessel));

        $this->assertDatabaseHas('vessels', ['id' => $vessel->id, 'name' => 'Río Claro II', 'base_port_id' => $port->id, 'gross_tonnage' => '32.50', 'hull_material' => 'Aluminio']);
    }
}
