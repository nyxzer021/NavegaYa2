<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCompanyIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_shows_weekly_departures_and_lowest_web_price_for_active_companies(): void
    {
        app()->setLocale('es');
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Zeta Navegacion', 'ruc' => '20888888885', 'status' => 'active', 'commission_rate' => 10]);
        Organization::create(['type' => 'transport_company', 'legal_name' => 'Alfa Sin Salidas', 'ruc' => '20888888886', 'status' => 'active']);
        Organization::create(['type' => 'transport_company', 'legal_name' => 'Empresa Inactiva', 'ruc' => '20888888887', 'status' => 'inactive']);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto']);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Lancha', 'registration_number' => 'NY-01', 'vessel_type' => 'lancha', 'seat_capacity' => 20, 'status' => 'ready']);

        foreach ([[1, 100, 'scheduled', true], [2, 80, 'scheduled', true], [3, 1, 'cancelled', true], [4, 1, 'scheduled', false]] as [$day, $fare, $status, $published]) {
            RouteDeparture::create([
                'transport_route_id' => $route->id,
                'vessel_id' => $vessel->id,
                'departure_at' => now()->addDays($day),
                'fare' => $fare,
                'status' => $status,
                'is_published' => $published,
            ]);
        }

        $response = $this->get(route('companies.index'))->assertOk()
            ->assertSeeInOrder(['Zeta Navegacion', '2 salidas esta semana', 'desde S/ 88.00', 'Alfa Sin Salidas'])
            ->assertDontSee('0 salidas')
            ->assertDontSee('0 rutas')
            ->assertDontSee('Empresa Inactiva');

        $cards = $response->viewData('companies')->values();
        $this->assertSame(0, $cards[1]['weekly']);
        $this->assertNull($cards[1]['price']);
    }
}
