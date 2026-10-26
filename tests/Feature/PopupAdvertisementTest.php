<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PopupAdvertisementTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_responsive_popup_and_counts_one_real_impression_per_session(): void
    {
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Lodge Popup SAC',
            'commercial_name' => 'Lodge Popup', 'ruc' => '20123456789', 'status' => 'active',
        ]);
        $advertisement = Advertisement::create([
            'organization_id' => $organization->id, 'business_name' => 'Lodge Popup',
            'business_type' => 'lodge_hotel', 'contact_name' => 'Ana', 'phone_whatsapp' => '999999999',
            'placements' => ['home_popup'], 'category' => 'Lodge / hotel', 'title' => 'Descansa frente al Amazonas',
            'description' => 'Una experiencia seleccionada para viajeros.', 'target_url' => 'https://example.test/lodge',
            'image_url' => 'https://example.test/promo.jpg', 'monthly_fee' => 0, 'status' => 'active',
            'starts_on' => today(), 'ends_on' => today()->addDays(30), 'views' => 0, 'clicks' => 0,
        ]);

        $this->get(route('home'))->assertOk()->assertSee('promotional-popup')->assertSee('Descansa frente al Amazonas');
        $this->post(route('discover.ad.impression', $advertisement))->assertNoContent();
        $this->post(route('discover.ad.impression', $advertisement))->assertNoContent();
        $this->assertSame(1, $advertisement->fresh()->views);

        $this->get(route('home', ['origin' => 'Iquitos']))->assertOk()->assertDontSee('promotional-popup');
    }

    public function test_advertiser_can_submit_popup_only_when_included_in_its_plan(): void
    {
        Storage::fake('public');
        $role = Role::firstOrCreate(['code' => 'marketplace_admin'], ['name' => 'Administrador de anunciante']);
        $city = DestinationCity::where('name', 'Iquitos')->firstOrFail();

        $makeAdvertiser = function (string $ruc, string $planCode) use ($role, $city): array {
            $plan = SubscriptionPlan::where('code', $planCode)->firstOrFail();
            $organization = Organization::create(['type' => 'marketplace_business', 'legal_name' => "Negocio {$ruc}", 'commercial_name' => "Negocio {$ruc}", 'ruc' => $ruc, 'status' => 'active']);
            $user = User::factory()->create(['email_verified_at' => now()]);
            $user->organizations()->attach($organization->id, ['status' => 'active']);
            $user->roles()->attach($role->id, ['organization_id' => $organization->id]);
            Subscription::create(['organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'monthly_price' => $plan->monthly_price, 'currency' => 'PEN', 'benefits_snapshot' => $plan->benefits, 'starts_on' => today(), 'ends_on' => today()->addMonth()]);
            DestinationListing::create(['organization_id' => $organization->id, 'owner_user_id' => $user->id, 'destination_city_id' => $city->id, 'type' => 'lodging', 'status' => 'published', 'name' => "Negocio {$ruc}", 'is_active' => true]);
            return [$user, $organization];
        };

        [$professional, $professionalOrganization] = $makeAdvertiser('20111111111', 'professional');
        $this->actingAs($professional)->get(route('marketplace.dashboard', ['tab' => 'campaigns']))->assertOk()->assertSee('ventana promocional');
        $this->actingAs($professional)->post(route('marketplace.campaigns.store'), ['title' => 'Oferta emergente', 'placement' => 'home_popup', 'banner_image' => UploadedFile::fake()->image('popup.jpg', 1400, 900)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('advertisements', ['organization_id' => $professionalOrganization->id, 'status' => 'lead_pending']);
        $professionalCampaign = Advertisement::where('organization_id', $professionalOrganization->id)->firstOrFail();
        $this->assertSame(['home_popup'], $professionalCampaign->placements);
        $this->assertStringEndsWith('.webp', $professionalCampaign->banner_image_path);
        Storage::disk('public')->assertExists($professionalCampaign->banner_image_path);
        [$storedWidth, $storedHeight] = getimagesize(Storage::disk('public')->path($professionalCampaign->banner_image_path));
        $this->assertSame(1200, $storedWidth);
        $this->assertSame(675, $storedHeight);

        [$basic] = $makeAdvertiser('20222222222', 'basic');
        $this->actingAs($basic)->post(route('marketplace.campaigns.store'), ['title' => 'Intento no permitido', 'placement' => 'home_popup', 'banner_image' => UploadedFile::fake()->image('popup-basic.jpg', 1600, 900)])->assertSessionHasErrors('placement');
        $this->actingAs($basic)->post(route('marketplace.campaigns.store'), ['title' => 'Imagen pequeña', 'placement' => 'home_carousel', 'banner_image' => UploadedFile::fake()->image('small.jpg', 640, 360)])->assertSessionHasErrors('banner_image');
    }
}
