<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
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
                ->assertSee('Consultar boleto')
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

    public function test_support_whatsapp_from_settings_appears_above_sign_in(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('wa.me/51987654321', false);

        SystemSetting::create(['key' => 'whatsapp_number', 'value' => '987654321']);

        $html = $this->get(route('home'))->assertOk()->assertSee('https://wa.me/51987654321', false)->assertSee('WhatsApp +51 987 654 321')->getContent();
        $this->assertLessThan(strpos($html, 'Iniciar sesión'), strpos($html, 'https://wa.me/51987654321'));
    }

    public function test_company_registration_is_highlighted_only_during_affiliation(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('aria-current="page"', false);
        $this->get(route('company.registration'))->assertOk()->assertSee('aria-current="page"', false);
    }
}
