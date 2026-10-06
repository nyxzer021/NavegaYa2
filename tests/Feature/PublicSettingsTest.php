<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_admin_can_update_header_and_footer_content(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador principal']));
        $this->actingAs($admin)->patch(route('admin.settings.chrome'), [
            'site_brand' => 'NavegaYA Perú',
            'footer_description' => 'Viajes por los ríos del Perú.',
            'footer_rights' => 'Derechos reservados.',
        ])->assertRedirect();
        $this->get(route('home'))->assertOk()->assertSee('NavegaYA Perú')->assertSee('Viajes por los ríos del Perú.');
    }

    public function test_visitor_can_choose_english_navigation(): void
    {
        $this->get(route('public.locale', ['locale' => 'en']))->assertRedirect();
        $this->get(route('routes.index'))->assertOk()->assertSee('River routes')->assertSee('Sign in');
    }
}
