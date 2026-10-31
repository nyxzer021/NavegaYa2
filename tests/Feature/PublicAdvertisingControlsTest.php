<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAdvertisingControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seventh_carousel_campaign_cannot_be_activated(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador principal']));

        foreach (range(1, 6) as $index) {
            Advertisement::create($this->campaign("Campaña {$index}", 'active'));
        }
        $seventh = Advertisement::create($this->campaign('Campaña 7', 'paused'));

        $this->actingAs($admin)->patch(route('admin.advertisements.resume', $seventh))
            ->assertSessionHasErrors('status');
        $this->assertSame('paused', $seventh->fresh()->status);
    }

    public function test_home_contains_accessible_carousel_and_side_banner(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('data-ad-carousel', false)
            ->assertSee('prefers-reduced-motion', false)
            ->assertSee('Anuncie aquí');
    }

    private function campaign(string $name, string $status): array
    {
        return ['business_name' => $name, 'business_type' => 'lodge_hotel', 'contact_name' => 'Contacto', 'phone_whatsapp' => '999999999', 'city_destination' => 'Iquitos', 'placements' => ['home_carousel'], 'category' => 'Hotel', 'title' => $name, 'status' => $status];
    }
}
