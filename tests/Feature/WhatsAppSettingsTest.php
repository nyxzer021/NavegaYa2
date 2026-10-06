<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_admin_can_configure_public_whatsapp(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador principal']));
        $this->actingAs($admin)->patch(route('admin.settings.whatsapp'), ['whatsapp_number' => '51999999999', 'whatsapp_message' => 'Hola, deseo ayuda con mi viaje.'])->assertRedirect();
        $this->assertDatabaseHas('system_settings', ['key' => 'whatsapp_number', 'value' => '51999999999']);
    }
}
