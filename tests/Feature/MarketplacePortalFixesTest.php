<?php

namespace Tests\Feature;

use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplacePortalFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_observed_page_shows_its_label_and_the_reason_to_the_business(): void
    {
        [$owner, $listing] = $this->business();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patch(route('admin.tourism-partners.moderate', $listing), [
            'decision' => 'observe', 'moderation_notes' => 'Falta el horario de los domingos.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('observed', $listing->fresh()->status);

        $this->actingAs($owner)->get(route('marketplace.dashboard', ['tab' => 'profile']))
            ->assertOk()
            ->assertSee('Observada')
            ->assertSee('NavegaYA observó tu página.')
            ->assertSee('Falta el horario de los domingos.')
            ->assertDontSee('>observed<', false);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'listing.observe',
            'organization_id' => $listing->organization_id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_the_success_message_appears_once_and_the_header_buttons_have_icons(): void
    {
        [$owner] = $this->business();

        $html = $this->actingAs($owner)->withSession(['success' => 'Cuenta creada de prueba.'])
            ->get(route('marketplace.dashboard', ['tab' => 'plan']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Cuenta creada de prueba.'));
        $this->assertMatchesRegularExpression('/aria-label="Mensajes comerciales".*?<svg/s', $html);
        $this->assertMatchesRegularExpression('/aria-label="Respuestas nuevas de soporte".*?<svg/s', $html);
    }

    public function test_the_portal_uses_icons_readable_text_and_guides_the_next_step(): void
    {
        [$owner, $listing] = $this->business('pending');
        $listing->update(['status' => 'draft']);

        $html = $this->actingAs($owner)->get(route('marketplace.dashboard', ['tab' => 'overview']))
            ->assertOk()
            ->assertSee('Pasos para publicar tu negocio')
            ->assertSee('0 de 5 listos', false)
            ->assertSee('Sigue con: <strong class="text-slate-800">Activa tu plan</strong>', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/text-\[(?:7|8|9|10|11)px\]/', $html);
        $this->assertDoesNotMatchRegularExpression('/[▦▣▧▤◉◆↗]/u', $html);
        $this->assertGreaterThanOrEqual(7, substr_count($html, 'class="mk-nav-icon"'));
    }

    public function test_a_published_business_no_longer_sees_the_steps(): void
    {
        [$owner, $listing] = $this->business();
        $listing->update(['status' => 'published', 'description' => 'Hotel frente al río.', 'contact_phone' => '965000111', 'opening_schedule' => ['mon' => ['closed' => false]]]);

        $this->actingAs($owner)->get(route('marketplace.dashboard', ['tab' => 'overview']))
            ->assertOk()
            ->assertDontSee('Pasos para publicar tu negocio');
    }

    public function test_the_plan_names_are_readable_in_the_registration(): void
    {
        $this->get(route('marketplace.register'))
            ->assertOk()
            ->assertSee('ml-2 text-base text-white', false);
    }

    public function test_manual_subscription_activation_is_recorded_for_the_business(): void
    {
        [, $listing] = $this->business('pending');
        $admin = $this->superAdmin();
        $plan = SubscriptionPlan::where('code', 'basic')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.advertising-subscriptions.store'), [
            'organization_id' => $listing->organization_id,
            'subscription_plan_id' => $plan->id,
            'starts_on' => today()->toDateString(),
            'months' => 1,
            'reason' => 'Pagó por transferencia, operación 123.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'subscription.manual_activation',
            'organization_id' => $listing->organization_id,
        ]);
    }

    /**
     * @return array{0: User, 1: DestinationListing}
     */
    private function business(string $subscriptionStatus = 'active'): array
    {
        $plan = SubscriptionPlan::where('code', 'basic')->firstOrFail();
        $city = DestinationCity::where('name', 'Iquitos')->firstOrFail();
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Hotel Prueba SAC', 'commercial_name' => 'Hotel Prueba',
            'ruc' => '20699111222', 'email' => 'hotel@prueba.test', 'phone' => '965000111', 'status' => 'pending',
        ]);
        $owner = User::factory()->create();
        $owner->organizations()->attach($organization->id, ['status' => 'pending']);
        $owner->roles()->attach(Role::where('code', 'marketplace_admin')->firstOrFail()->id, ['organization_id' => $organization->id]);
        Subscription::create([
            'organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => $subscriptionStatus,
            'monthly_price' => $plan->monthly_price, 'currency' => 'PEN', 'starts_on' => today(), 'ends_on' => today()->addMonth(),
        ]);
        $listing = DestinationListing::create([
            'organization_id' => $organization->id, 'owner_user_id' => $owner->id, 'destination_city_id' => $city->id,
            'type' => 'lodging', 'status' => 'pending_review', 'name' => 'Hotel Prueba', 'sort_order' => 99, 'is_active' => false,
        ]);

        return [$owner, $listing];
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador'])->id);

        return $admin;
    }
}
