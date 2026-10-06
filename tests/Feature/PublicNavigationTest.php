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
        foreach (['home', 'routes.index', 'discover.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Rutas y horarios')->assertSee('Empresas')->assertSee('Puertos y muelles')->assertSee('Consultar pasaje')->assertSee('Vende tus pasajes')->assertSee('Ingresar');
        }
    }

    public function test_public_navigation_shows_account_access_with_an_active_session(): void
    {
        $user = User::factory()->create(['name' => 'Daniel Reyna']);
        $this->actingAs($user)->get(route('routes.index'))->assertOk()->assertSee('Mi cuenta')->assertDontSee('Ingresar');
    }
}
