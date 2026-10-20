<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Advertisement::query()->delete();

        Advertisement::create([
            'business_name' => 'Tour Amazonas', 'business_type' => 'tour_operator', 'contact_name' => 'Ventas',
            'phone_whatsapp' => '999999999', 'city_destination' => 'Iquitos', 'placements' => ['home_carousel'],
            'category' => 'Tour', 'title' => 'Explora el Amazonas', 'status' => 'active',
            'starts_on' => today()->subDay(), 'ends_on' => today()->addDay(),
        ]);
        Advertisement::create([
            'business_name' => 'Campaña vencida', 'business_type' => 'restaurant', 'contact_name' => 'Ventas',
            'phone_whatsapp' => '988888888', 'city_destination' => 'Iquitos', 'placements' => ['home_carousel'],
            'category' => 'Restaurante', 'title' => 'Campaña vencida', 'status' => 'active',
            'ends_on' => today()->subDay(),
        ]);

        $this->assertSame(1, Advertisement::currentlyActive()->forPlacement('home_carousel')->count());

        $this->get(route('home'))->assertOk()->assertSee('Explora el Amazonas')->assertDontSee('Campaña vencida');
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

    public function test_super_admin_can_register_and_publish_a_home_campaign_with_an_image(): void
    {
        Storage::fake('public');
        Advertisement::query()->delete();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']));

        $this->actingAs($admin)->get(route('admin.ads.index'))
            ->assertOk()
            ->assertSee('Registrar campaña publicitaria')
            ->assertSee('Portada · carrusel principal')
            ->assertSee('Portada · banner lateral');

        $this->actingAs($admin)->post(route('admin.ads.store'), [
            'business_name' => 'Lodge de prueba',
            'business_type' => 'lodge_hotel',
            'contact_name' => 'María Pérez',
            'phone_whatsapp' => '999999999',
            'city_destination' => 'Nauta',
            'placements' => ['home_hero'],
            'title' => 'Hospedaje frente al río',
            'description' => 'Campaña creada desde el panel administrativo.',
            'target_url' => 'https://example.test/lodge',
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addMonth()->toDateString(),
            'status' => 'active',
            'banner_image' => UploadedFile::fake()->image('lodge.webp', 1200, 700),
        ])->assertRedirect();

        $campaign = Advertisement::where('business_name', 'Lodge de prueba')->firstOrFail();
        $this->assertSame('active', $campaign->status);
        $this->assertSame(['home_hero'], $campaign->placements);
        Storage::disk('public')->assertExists($campaign->banner_image_path);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Hospedaje frente al río')
            ->assertSee('Patrocinado');
    }

    public function test_home_displays_a_carousel_and_one_stable_sidebar_ad_without_duplicates(): void
    {
        Advertisement::query()->delete();
        $carousel = Advertisement::create([
            'business_name' => 'Hotel Carrusel', 'business_type' => 'lodge_hotel', 'contact_name' => 'Ventas',
            'phone_whatsapp' => '999999999', 'city_destination' => 'Iquitos',
            'placements' => ['home_carousel', 'home_sidebar'], 'category' => 'Hotel',
            'title' => 'Descansa junto al Amazonas', 'target_url' => 'https://example.test/hotel', 'status' => 'active',
        ]);
        $sidebar = Advertisement::create([
            'business_name' => 'Restaurante Lateral', 'business_type' => 'restaurant', 'contact_name' => 'Ventas',
            'phone_whatsapp' => '988888888', 'city_destination' => 'Iquitos',
            'placements' => ['home_sidebar'], 'category' => 'Restaurante',
            'title' => 'Sabores de Loreto', 'target_url' => 'https://example.test/restaurante', 'status' => 'active',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Descansa junto al Amazonas')
            ->assertSee('Sabores de Loreto');

        $this->assertSame($sidebar->id, session('home_sidebar_ad_id'));
        $this->assertSame(1, $carousel->fresh()->views);
        $this->assertSame(1, $sidebar->fresh()->views);
        $this->get(route('home'))->assertOk()->assertSee('Sabores de Loreto');
        $this->assertSame($sidebar->id, session('home_sidebar_ad_id'));
    }
}
