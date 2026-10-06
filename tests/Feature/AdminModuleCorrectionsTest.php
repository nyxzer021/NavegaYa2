<?php

namespace Tests\Feature;

use App\Models\MasterRoute;
use App\Models\Port;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterRouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModuleCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_yurimaguas_nauta_master_route_is_repaired_by_the_seeder(): void
    {
        $iquitos = Port::create(['name' => 'Puerto de Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $nauta = Port::create(['name' => 'Puerto de Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $yurimaguas = Port::create(['name' => 'Puerto de Yurimaguas', 'city' => 'Yurimaguas', 'region' => 'Loreto', 'is_active' => true]);

        MasterRoute::create([
            'code' => 'TRM-F-3-1',
            'modality' => 'fluvial',
            'origin_city' => 'Yurimaguas',
            'destination_city' => 'Nauta',
            'origin_port_id' => $yurimaguas->id,
            'destination_port_id' => $iquitos->id,
            'status' => 'active',
        ]);

        $this->seed(MasterRouteSeeder::class);

        $this->assertDatabaseHas('master_routes', [
            'code' => 'TRM-F-3-1',
            'destination_city' => 'Nauta',
            'destination_port_id' => $nauta->id,
            'river_basin' => 'Río Huallaga / Marañón',
        ]);
    }

    public function test_admin_settings_explains_the_commission_revenue_model(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Porcentaje de comisión retenido por NavegaYA sobre cada boleto comercializado a través de la pasarela web. Las empresas reciben el valor neto restante en su liquidación periódica.');
    }

    public function test_new_administrator_form_only_shows_platform_governance_gates(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Gobernanza y Aprobación de Empresas')
            ->assertSee('Gestión de Catálogo y Tramos Maestros')
            ->assertSee('Finanzas, Liquidaciones y Comisiones')
            ->assertSee('Directorio Turístico Guía Loreto')
            ->assertSee('Seguridad, Auditoría y Configuración')
            ->assertDontSee('Control de embarque')
            ->assertDontSee('Crear rutas y programar salidas');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));

        return $admin;
    }
}
