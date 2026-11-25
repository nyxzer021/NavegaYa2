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
            'admin.refunds.index', 'admin.claims.index', 'admin.moderation.index', 'admin.audit.index', 'admin.navigation.index',
            'admin.users.index', 'admin.settings.index',
        ];

        $this->actingAs($admin);
        foreach ($routes as $routeName) {
            $this->get(route($routeName))->assertSuccessful();
        }
    }

    public function test_policy_moderation_and_report_pages_have_their_own_tab_title(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $this->actingAs($admin);

        $expected = [
            'admin.base-policies.index' => 'Políticas base · NavegaYA',
            'admin.company-policies.index' => 'Políticas de empresas · NavegaYA',
            'admin.content-moderation.index' => 'Contenido por moderar · NavegaYA',
            'admin.reports.index' => 'Reportes · NavegaYA',
        ];
        foreach ($expected as $routeName => $title) {
            $this->get(route($routeName))->assertOk()->assertSee('<title>'.$title.'</title>', false);
        }
    }
}
