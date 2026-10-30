<?php

namespace Tests\Feature;

use App\Models\NavigationItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_page_and_assign_it_to_public_menu(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['code' => 'super_admin', 'name' => 'Super administrador']);
        $admin->roles()->attach($role);
        $this->actingAs($admin)->get(route('admin.navigation.index'))->assertOk()->assertSee('Menú y páginas');
        $this->actingAs($admin)->post(route('admin.navigation.pages.store'), ['title' => 'Preguntas frecuentes', 'slug' => 'preguntas', 'summary' => 'Ayuda', 'content' => 'Contenido útil', 'is_published' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.navigation.items.store'), ['label' => 'Ayuda', 'label_en' => 'Help', 'target_type' => 'page', 'target' => 'preguntas', 'position' => 60, 'is_active' => 1])->assertRedirect();
        $this->get(route('pages.show', 'preguntas'))->assertOk()->assertSee('Preguntas frecuentes')->assertSee('Contenido útil')->assertSee('Ayuda');
        $this->get(route('home'))->assertOk()->assertSee('Ayuda')->assertSee(route('pages.show', 'preguntas'));
    }

    public function test_navigation_rejects_a_system_route_as_a_custom_page(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['code' => 'super_admin', 'name' => 'Super administrador']);
        $admin->roles()->attach($role);

        $this->actingAs($admin)->post(route('admin.navigation.items.store'), [
            'label' => 'Destino inválido',
            'target_type' => 'page',
            'target' => 'discover.index',
            'position' => 10,
            'is_active' => 1,
        ])->assertSessionHasErrors('target');
    }

    public function test_inactive_navigation_item_is_not_rendered(): void
    {
        NavigationItem::query()->update(['is_active' => false]);
        $this->get(route('home'))->assertOk()->assertDontSee('Rutas fluviales');
    }
}
