<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplacePlanVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El plan vence según el día de Lima; a medianoche UTC "ayer" en UTC todavía es hoy en Lima.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setTime(17, 0));
    }

    public function test_business_page_and_campaign_disappear_when_the_plan_expires_and_return_after_renewal(): void
    {
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Lodge Vigente SAC',
            'commercial_name' => 'Lodge Vigente', 'ruc' => '20111222333', 'status' => 'active',
        ]);
        $plan = SubscriptionPlan::where('code', 'basic')->firstOrFail();
        $subscription = Subscription::create([
            'organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => 'active',
            'monthly_price' => $plan->monthly_price, 'currency' => 'PEN',
            'starts_on' => today()->subMonths(2), 'ends_on' => today()->subDay(),
        ]);
        $listing = DestinationListing::create([
            'organization_id' => $organization->id,
            'destination_city_id' => DestinationCity::where('name', 'Iquitos')->firstOrFail()->id,
            'type' => 'lodging', 'status' => 'published', 'is_active' => true,
            'name' => 'Lodge Vigente', 'contact_phone' => '987654321', 'sort_order' => 1,
        ]);
        $advertisement = Advertisement::create([
            'organization_id' => $organization->id, 'business_name' => 'Lodge Vigente', 'business_type' => 'lodge_hotel',
            'contact_name' => 'Ana', 'phone_whatsapp' => '987654321', 'placements' => ['home_carousel'], 'category' => 'Lodge / hotel',
            'title' => 'Campaña del lodge', 'target_url' => route('guide.show', $listing),
            'monthly_fee' => 0, 'status' => 'active', 'views' => 0, 'clicks' => 0,
        ]);

        $this->get(route('guide.index'))->assertOk()->assertDontSee(route('guide.show', $listing), false);
        $this->get(route('guide.show', $listing))->assertNotFound();
        $this->get(route('discover.ad', $advertisement))->assertNotFound();

        $subscription->update(['ends_on' => today()->addMonth()]);

        $this->get(route('guide.index'))->assertOk()->assertSee(route('guide.show', $listing), false);
        $this->get(route('guide.show', $listing))->assertOk()->assertSee('Lodge Vigente');
        $this->get(route('discover.ad', $advertisement))->assertRedirect(route('guide.show', $listing));
    }

    public function test_listing_created_by_the_super_admin_opens_without_a_business_account(): void
    {
        $listing = DestinationListing::create([
            'destination_city_id' => DestinationCity::where('name', 'Iquitos')->firstOrFail()->id,
            'type' => 'attraction', 'status' => 'published', 'is_active' => true,
            'name' => 'Mirador del Itaya', 'sort_order' => 1,
        ]);

        $this->get(route('guide.show', $listing))->assertOk()->assertSee('Mirador del Itaya');
    }
}
