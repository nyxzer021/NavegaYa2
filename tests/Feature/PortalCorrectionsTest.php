<?php

namespace Tests\Feature;

use App\Models\DestinationCity;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\SearchDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_descriptions_stay_between_140_and_160_characters(): void
    {
        $fitted = SearchDescription::fit('Corta');
        $this->assertGreaterThanOrEqual(140, mb_strlen($fitted));
        $this->assertLessThanOrEqual(160, mb_strlen($fitted));

        foreach (['/', '/login', '/register', '/forgot-password', '/quienes-somos'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            preg_match('/name="description" content="([^"]*)"/', $html, $matches);
            $description = html_entity_decode($matches[1] ?? '', ENT_QUOTES);
            $this->assertGreaterThanOrEqual(140, mb_strlen($description), $path);
            $this->assertLessThanOrEqual(160, mb_strlen($description), $path);
        }

        $this->get('/login')->assertSee('og:description', false);
    }

    public function test_seeded_cities_outside_loreto_stay_out_of_discover(): void
    {
        $this->get('/descubrir')
            ->assertOk()
            ->assertDontSee('Pucallpa')
            ->assertDontSee('Tarapoto')
            ->assertDontSee('Puerto Maldonado')
            ->assertSee('Iquitos');
    }

    public function test_discover_cards_show_a_photo_and_hide_cities_outside_the_catalog(): void
    {
        DestinationCity::create(['name' => 'Ciudad Sin Foto', 'department' => 'Loreto', 'is_active' => true, 'show_in_discover' => true, 'sort_order' => 1]);
        DestinationCity::create(['name' => 'Ciudad Oculta', 'department' => 'Ucayali', 'is_active' => true, 'show_in_discover' => false, 'sort_order' => 2]);

        $this->get('/descubrir')
            ->assertOk()
            ->assertSee('Ciudad Sin Foto')
            ->assertSee('banner-descubrir-amazonia-v2.webp')
            ->assertDontSee('Ciudad Oculta');
    }

    public function test_traveler_profile_uses_the_public_site_in_spanish(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente / pasajero']));

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Datos de tu cuenta')
            ->assertSee('Eliminar cuenta')
            ->assertSee('Las reservas y los boletos se conservan')
            ->assertDontSee('Panel Administrador')
            ->assertDontSee('Profile Information');
    }

    public function test_forbidden_panel_page_sends_the_traveler_back_to_their_account(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente / pasajero']));

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('Acceso no autorizado')
            ->assertSee('Volver a mi panel')
            ->assertSee(route('travels.index'));
    }

    public function test_admin_pages_use_their_own_title(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']));

        $this->actingAs($user)->get(route('admin.destinations.index'))
            ->assertOk()
            ->assertSee('<title>Destinos · NavegaYA</title>', false);
    }

    public function test_company_portal_menu_and_sell_button_are_readable(): void
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Operador Visible',
            'ruc' => '20999999991',
            'email' => 'visible@test.pe',
            'status' => 'active',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']), ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()
            ->assertSee('Boletos vendidos')
            ->assertSee('Comisión del convenio')
            ->assertSee('Salidas de hoy')
            ->assertSee('Venta en muelle')
            ->assertDontSee('Vender pasaje')
            ->assertSee('company-menu-toggle', false)
            ->assertSee('data-menu-lead="operacion"', false)
            ->assertSee('text-amber-400', false)
            ->assertDontSee('Cobrado hoy')
            ->assertDontSee('text-[9px]', false);
    }
}
