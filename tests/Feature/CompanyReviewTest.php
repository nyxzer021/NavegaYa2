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
        $company = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Amazonas SAC', 'ruc' => '20999999991',
            'email' => 'amazonas@example.test', 'status' => 'active',
            'public_description' => 'Operador regional con atención diaria.',
            'cover_image_path' => '/storage/company-profiles/amazonas.jpg',
            'profile_content_status' => 'published',
        ]);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Amazonas', 'registration_number' => 'AM-001', 'vessel_type' => 'lancha', 'seat_capacity' => 10, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'IQT-NAU-REV']);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->subDay(), 'fare' => 50, 'status' => 'completed']);
        $user = User::factory()->create(['email' => 'viajero@example.test']);
        Reservation::create(['route_departure_id' => $departure->id, 'code' => 'NY-REVIEW', 'contact_name' => $user->name, 'contact_email' => $user->email, 'contact_phone' => '999888777', 'total_amount' => 50, 'status' => 'confirmed']);

        $this->actingAs($user)->post(route('companies.review', $company), [
            'reservation_id' => $reservation->id,
            'rating' => 5,
            'comment' => 'Excelente atención y embarcación muy cómoda.',
        ])->assertRedirect(route('companies.show', $company).'#opiniones');
        $this->assertDatabaseHas('company_reviews', [
            'organization_id' => $company->id,
            'user_id' => $user->id,
            'reservation_id' => $reservation->id,
            'rating' => 5,
            'status' => 'pending',
        ]);

        CompanyReview::firstOrFail()->update(['status' => 'published']);
        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('5.0')
            ->assertSee('Excelente atención')
            ->assertSee('Información de la empresa')
            ->assertSee('Operador regional con atención diaria.')
            ->assertSee('href="#flota"', false)
            ->assertSee('/storage/company-profiles/amazonas.jpg', false);
    }

    public function test_passenger_cannot_review_a_trip_that_is_not_completed(): void
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Río SAC', 'ruc' => '20999999992', 'email' => 'rio@example.test', 'status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Río I', 'registration_number' => 'RIO-001', 'vessel_type' => 'lancha', 'seat_capacity' => 10, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'IQT-NAU-PENDING']);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 50, 'status' => 'scheduled']);
        $user = User::factory()->create(['email' => 'pendiente@example.test']);
        $reservation = Reservation::create(['route_departure_id' => $departure->id, 'code' => 'NY-PENDING', 'contact_name' => $user->name, 'contact_email' => $user->email, 'contact_phone' => '999888777', 'total_amount' => 50, 'status' => 'confirmed']);

        $this->actingAs($user)->post(route('companies.review', $company), [
            'reservation_id' => $reservation->id,
            'rating' => 5,
            'comment' => 'Intento de opinión antes de completar el viaje.',
        ])->assertNotFound();

        $this->assertDatabaseCount('company_reviews', 0);
    }
}
