<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeHeroSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_admin_can_manage_home_hero_content(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador principal']));

        $this->actingAs($admin)->patch(route('admin.home-hero.duration'), ['duration' => 6])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.home-hero.store'), [
            'image_path' => '/images/nueva-portada.png',
            'title' => 'Nueva portada amazónica',
            'subtitle' => 'Contenido administrable.',
            'button_label' => 'Conocer',
            'button_url' => '#destinos',
            'sort_order' => 4,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('home_hero_slides', ['title' => 'Nueva portada amazónica', 'sort_order' => 4]);
        $this->get(route('home'))->assertOk()->assertSee('Nueva portada amazónica');
    }
}
