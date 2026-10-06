<?php

namespace Tests\Feature;

use App\Models\DestinationCity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_admin_can_create_featured_lodging(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador principal']));
        $city = DestinationCity::first();
        $this->actingAs($admin)->post(route('admin.destination-listings.store', 'lodging'), ['destination_city_id' => $city->id, 'name' => 'Lodge Río Verde', 'category' => 'Lodge', 'sort_order' => 1, 'is_active' => 1, 'is_featured' => 1])->assertRedirect();
        $this->assertDatabaseHas('destination_listings', ['name' => 'Lodge Río Verde', 'type' => 'lodging', 'is_featured' => true]);
    }
}
