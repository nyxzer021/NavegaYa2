<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_public_temperature_and_river_status(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador principal']));

        $this->actingAs($admin)->patch(route('admin.settings.operations'), [
            'weather_temperature' => 31,
            'river_status' => 'rising',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('system_settings', ['key' => 'weather_temperature', 'value' => '31']);
        $this->assertDatabaseHas('system_settings', ['key' => 'river_status', 'value' => 'rising']);
        $this->get(route('home'))->assertOk()->assertSee('31°C')->assertSee('Nivel del río')->assertSee('En aumento');
    }

    public function test_operational_settings_reject_invalid_values(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador principal']));

        $this->actingAs($admin)->patch(route('admin.settings.operations'), [
            'weather_temperature' => 80,
            'river_status' => 'dangerous',
        ])->assertSessionHasErrors(['weather_temperature', 'river_status']);
    }
}
