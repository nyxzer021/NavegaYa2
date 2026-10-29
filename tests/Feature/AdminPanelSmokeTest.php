<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_primary_super_admin_page_renders(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));

        $routes = [
            'admin.dashboard', 'admin.requests.index', 'admin.companies.index', 'admin.fleet-compliance.index',
            'admin.ports.index', 'admin.master-routes.index', 'admin.itineraries.index', 'admin.business-categories.index',
            'admin.tourism-partners.index', 'admin.tours.index', 'admin.advertisements.index',
            'admin.advertising-subscriptions.index', 'admin.sales.index', 'admin.commissions.index', 'admin.cargo.index',
            'admin.refunds.index', 'admin.moderation.index', 'admin.audit.index', 'admin.navigation.index',
            'admin.users.index', 'admin.settings.index',
        ];

        $this->actingAs($admin);
        foreach ($routes as $routeName) {
            $this->get(route($routeName))->assertSuccessful();
        }
    }
}
