<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
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
        $category = BusinessCategory::where('code', 'lodge')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.destination-listings.store', 'lodging'), ['destination_city_id' => $city->id, 'business_category_id' => $category->id, 'name' => 'Lodge Río Verde'])->assertRedirect();
        $this->assertDatabaseHas('destination_listings', ['name' => 'Lodge Río Verde', 'type' => 'lodging', 'status' => 'draft']);
    }
}
