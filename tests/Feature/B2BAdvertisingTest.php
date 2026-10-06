<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2BAdvertisingTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_submit_an_advertising_lead(): void
    {
        $this->get(route('advertising.create'))
            ->assertOk()
            ->assertSee('Haz visible tu negocio ante viajeros que exploran Loreto')
            ->assertSee('Atención comercial inmediata por WhatsApp');

        $this->post(route('advertising.store'), [
            'business_name' => 'Lodge Río Dorado',
            'business_type' => 'lodge_hotel',
            'contact_name' => 'Ana Pérez',
            'phone_whatsapp' => '+51 999 111 222',
            'email' => 'ventas@riodorado.test',
            'city_destination' => 'Iquitos',
            'ruc' => '20123456789',
            'placements' => ['home_hero', 'routes_sidebar'],
            'target_url' => 'https://example.test/lodge',
        ])->assertRedirect(route('advertising.create'));

        $this->assertSame(
            '¡Solicitud recibida con éxito! Nuestro equipo comercial se comunicará a tu WhatsApp en menos de 24 horas.',
            session('success_lead')
        );

        $this->assertDatabaseHas('advertisements', [
            'business_name' => 'Lodge Río Dorado',
            'status' => 'lead_pending',
            'monthly_fee' => 300,
        ]);
    }

    public function test_only_active_current_campaigns_are_selected_for_a_placement(): void
    {
        Advertisement::create([
            'business_name' => 'Tour Amazonas', 'business_type' => 'tour_operator', 'contact_name' => 'Ventas',
            'phone_whatsapp' => '999999999', 'city_destination' => 'Iquitos', 'placements' => ['home_hero'],
            'category' => 'Tour', 'title' => 'Explora el Amazonas', 'status' => 'active',
            'starts_on' => today()->subDay(), 'ends_on' => today()->addDay(),
        ]);
        Advertisement::create([
            'business_name' => 'Campaña vencida', 'business_type' => 'restaurant', 'contact_name' => 'Ventas',
            'phone_whatsapp' => '988888888', 'city_destination' => 'Iquitos', 'placements' => ['home_hero'],
            'category' => 'Restaurante', 'title' => 'Campaña vencida', 'status' => 'active',
            'ends_on' => today()->subDay(),
        ]);

        $this->assertSame(1, Advertisement::currentlyActive()->forPlacement('home_hero')->count());

        $this->get(route('home'))->assertOk()->assertSee('Tour Amazonas')->assertDontSee('Campaña vencida');
    }

    public function test_super_admin_can_approve_a_lead(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']));
        $ad = Advertisement::create([
            'business_name' => 'Restaurante Paiche', 'business_type' => 'restaurant', 'contact_name' => 'Gerencia',
            'phone_whatsapp' => '977777777', 'city_destination' => 'Iquitos', 'placements' => ['company_footer'],
            'category' => 'Restaurante', 'title' => 'Restaurante Paiche', 'status' => 'lead_pending',
        ]);

        $this->actingAs($admin)->patch(route('admin.ads.approve', $ad))->assertRedirect();
        $this->assertDatabaseHas('advertisements', ['id' => $ad->id, 'status' => 'active']);
    }
}
