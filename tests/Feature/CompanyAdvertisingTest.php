<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyAdvertisingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transport_company_can_open_advertising_panel_and_submit_included_campaign(): void
    {
        Storage::fake('public');
        [$user, $organization] = $this->company();
        $plan = SubscriptionPlan::create(['code' => 'transport-pro', 'name' => 'Operador Pro', 'monthly_price' => 300, 'currency' => 'PEN', 'is_active' => true, 'benefits' => ['ad_limit' => 2, 'allowed_placements' => ['home_carousel', 'routes_sidebar']]]);
        Subscription::create(['organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'monthly_price' => 300, 'currency' => 'PEN', 'starts_on' => today(), 'benefits_snapshot' => $plan->benefits]);

        $this->actingAs($user)->get(route('company.advertising.index'))
            ->assertOk()->assertSee('Promociona tus rutas y servicios')->assertSee('Portada · carrusel principal');

        $this->actingAs($user)->post(route('company.advertising.store'), [
            'title' => 'Viaja seguro por el Amazonas', 'description' => 'Salidas verificadas desde Iquitos.',
            'placement' => 'home_carousel', 'banner_image' => UploadedFile::fake()->image('ruta.jpg', 1200, 675),
        ])->assertRedirect();

        $campaign = Advertisement::where('organization_id', $organization->id)->firstOrFail();
        $this->assertSame($organization->id, $campaign->organization_id);
        $this->assertSame('lead_pending', $campaign->status);
        $this->assertSame(0.0, (float) $campaign->monthly_fee);
        Storage::disk('public')->assertExists($campaign->banner_image_path);
    }

    public function test_transport_company_cannot_use_a_placement_outside_its_plan(): void
    {
        [$user, $organization] = $this->company();
        $plan = SubscriptionPlan::create(['code' => 'transport-basic', 'name' => 'Operador Básico', 'monthly_price' => 100, 'currency' => 'PEN', 'is_active' => true, 'benefits' => ['ad_limit' => 1, 'allowed_placements' => ['routes_sidebar']]]);
        Subscription::create(['organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'monthly_price' => 100, 'currency' => 'PEN', 'starts_on' => today(), 'benefits_snapshot' => $plan->benefits]);

        $this->actingAs($user)->from(route('company.advertising.index'))->post(route('company.advertising.store'), [
            'title' => 'Campaña no incluida', 'placement' => 'home_popup',
            'banner_image' => UploadedFile::fake()->image('ruta.jpg', 1200, 675),
        ])->assertSessionHasErrors('placement');
        $this->assertDatabaseMissing('advertisements', ['organization_id' => $organization->id, 'title' => 'Campaña no incluida']);
    }

    public function test_pending_transport_company_cannot_access_advertising(): void
    {
        [$user, $organization] = $this->company();
        $organization->update(['status' => 'pending']);

        $this->actingAs($user)->get(route('company.advertising.index'))->assertNotFound();
    }

    private function company(): array
    {
        $organization = Organization::create(['type' => 'transport_company', 'modality' => 'fluvial', 'legal_name' => 'Río Seguro SAC', 'commercial_name' => 'Río Seguro', 'ruc' => '20123456789', 'email' => 'rio@test.pe', 'phone' => '999999999', 'base_city' => 'Iquitos', 'status' => 'active']);
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $user->roles()->attach($role, ['organization_id' => $organization->id]);
        $user->organizations()->attach($organization, ['status' => 'active']);

        return [$user, $organization];
    }
}
