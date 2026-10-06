<?php

namespace Tests\Feature;

use App\Models\CompanyReview;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_can_submit_one_pending_review_after_completed_trip(): void
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Amazonas SAC', 'ruc' => '20999999991', 'email' => 'amazonas@example.test', 'status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Amazonas', 'registration_number' => 'AM-001', 'vessel_type' => 'lancha', 'seat_capacity' => 10, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'IQT-NAU-REV']);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->subDay(), 'fare' => 50, 'status' => 'completed']);
        $user = User::factory()->create(['email' => 'viajero@example.test']);
        Reservation::create(['route_departure_id' => $departure->id, 'code' => 'NY-REVIEW', 'contact_name' => $user->name, 'contact_email' => $user->email, 'contact_phone' => '999888777', 'total_amount' => 50, 'status' => 'confirmed']);

        $this->actingAs($user)->post(route('companies.review', $company), ['rating' => 5, 'comment' => 'Excelente atención y embarcación muy cómoda.'])->assertRedirect();
        $this->assertDatabaseHas('company_reviews', ['organization_id' => $company->id, 'user_id' => $user->id, 'rating' => 5, 'status' => 'pending']);

        CompanyReview::firstOrFail()->update(['status' => 'published']);
        $this->get(route('companies.show', $company))->assertOk()->assertSee('5.0')->assertSee('Excelente atención');
    }
}
