<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRouteCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_can_filter_public_scheduled_routes(): void
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Fluvial Demo', 'ruc' => '20777777777', 'email' => 'demo@test.pe', 'status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Nave Catálogo', 'registration_number' => 'CAT-01', 'vessel_type' => 'lancha', 'seat_capacity' => 20, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active']);
        RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'boarding_starts_at' => now()->addDay()->subHour(), 'fare' => 75, 'status' => 'scheduled']);
        $this->get(route('routes.index'))->assertOk()->assertSee('Nave Catálogo');
        $this->get(route('routes.index', ['origin' => 'Iquitos']))->assertOk()->assertSee('Iquitos → Nauta');
    }
}
