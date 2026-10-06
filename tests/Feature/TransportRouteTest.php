<?php

namespace Tests\Feature;

use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_monitor_is_read_only_and_links_to_master_catalog(): void
    {
        $super = User::factory()->create();
        $super->roles()->attach(Role::create(['code' => 'super_admin', 'name' => 'Super administrador']));

        $this->actingAs($super)->get(route('admin.transport-routes.index'))
            ->assertOk()->assertSee('Solo lectura')->assertSee('Tramos maestros')
            ->assertDontSee('Programar salida')->assertDontSee('Editar / Reprogramar');
        $this->actingAs($super)->get('/admin/rutas-salidas/crear')->assertNotFound();
    }

    public function test_company_departure_form_lists_only_active_master_routes(): void
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Operador Control SAC', 'ruc' => '20555666777', 'email' => 'control@test.pe', 'status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Muelle Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        MasterRoute::create(['code' => 'TRM-ACTIVA', 'modality' => 'fluvial', 'origin_city' => 'Iquitos', 'destination_city' => 'Nauta', 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active']);
        MasterRoute::create(['code' => 'TRM-SUSPENDIDA', 'modality' => 'fluvial', 'origin_city' => 'Nauta', 'destination_city' => 'Iquitos', 'origin_port_id' => $destination->id, 'destination_port_id' => $origin->id, 'status' => 'maintenance']);
        Vessel::create(['organization_id' => $company->id, 'name' => 'Río Azul', 'registration_number' => 'PA-8001', 'vessel_type' => 'lancha', 'seat_capacity' => 15, 'status' => 'ready']);
        $companyAdmin = User::factory()->create();
        $companyAdmin->roles()->attach(Role::create(['code' => 'company_admin', 'name' => 'Administrador empresa']), ['organization_id' => $company->id]);
        $companyAdmin->organizations()->attach($company->id, ['status' => 'active']);

        $this->actingAs($companyAdmin)->get(route('company.departures.create'))
            ->assertOk()->assertSee('TRM-ACTIVA')->assertDontSee('TRM-SUSPENDIDA')->assertSee('Tramo maestro oficial');
        $this->actingAs($companyAdmin)->get(route('admin.master-routes.create'))->assertForbidden();
    }
}
