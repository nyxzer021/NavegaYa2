<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_share_navigation_for_guests(): void
    {
        foreach (['home', 'routes.index', 'discover.index', 'about'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('Quiénes somos')
                ->assertSee('Rutas fluviales')
                ->assertSee('Empresas')
                ->assertSee('Descubrir')
                ->assertSee('Busca tu pasaje')
                ->assertSee('Afiliar tu empresa')
                ->assertSee('Iniciar sesión');
        }
    }

    public function test_about_page_uses_the_public_responsive_layout(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Conectamos viajes, ríos y personas')
            ->assertSee('Información clara para viajar mejor')
            ->assertSee('Explorar rutas');
    }

    public function test_public_navigation_shows_account_access_with_an_active_session(): void
    {
        $user = User::factory()->create(['name' => 'Daniel Reyna']);
        $this->actingAs($user)->get(route('routes.index'))->assertOk()->assertSee('Mi cuenta')->assertDontSee('Iniciar sesión');
    }
}
