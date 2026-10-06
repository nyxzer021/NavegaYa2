<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_public_scheduled_departure(): void
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Operador Inicio', 'ruc' => '20888888888', 'email' => 'inicio@test.pe', 'status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Nave Inicio', 'registration_number' => 'IN-01', 'vessel_type' => 'lancha', 'seat_capacity' => 20, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active']);
        RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'boarding_starts_at' => now()->addDay()->subHour(), 'fare' => 65, 'status' => 'scheduled']);
        $this->get(route('home'))->assertOk()->assertSee('Viaja por la Amazonía con total confianza')->assertSee('Iquitos')->assertSee('Nauta')
            ->assertSee('action="'.route('home').'#resultados"', false);

        $this->get(route('home', ['transport' => 'river', 'origin' => 'Iquitos', 'destination' => 'Nauta', 'passengers' => 2]))
            ->assertOk()->assertSee('Resultados de tu búsqueda')->assertSee('Iquitos')->assertSee('Nauta')
            ->assertSee('value="Iquitos" selected', false)->assertSee('value="2" selected', false);
    }
}
