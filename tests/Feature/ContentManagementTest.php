<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\DestinationCity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador principal']));

        return $user;
    }

    public function test_admin_can_update_destination_and_publish_campaign(): void
    {
        $city = DestinationCity::where('name', 'Iquitos')->firstOrFail();
        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.destinations.update', $city), ['department' => 'Loreto', 'river' => 'Amazonas', 'summary' => 'Capital amazónica.', 'attractions' => 'Belén', 'lodging' => 'Hotel prueba', 'gastronomy' => 'Juane', 'image_url' => 'https://example.com/iquitos.jpg', 'is_active' => 1])->assertRedirect(route('admin.destinations.index'));
        $this->assertDatabaseHas('destination_cities', ['id' => $city->id, 'summary' => 'Capital amazónica.']);
        $this->actingAs($admin)->post(route('admin.ads.store'), ['destination_city_id' => $city->id, 'business_name' => 'Hotel Río', 'category' => 'Hotel', 'title' => 'Hospédate en Iquitos', 'image_url' => 'https://example.com/hotel.jpg', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addMonth()->toDateString()])->assertRedirect();
        $ad = Advertisement::firstOrFail();
        $this->actingAs($admin)->patch(route('admin.ads.approve', $ad))->assertRedirect();
        $this->assertDatabaseHas('advertisements', ['id' => $ad->id, 'status' => 'active']);
    }
}
