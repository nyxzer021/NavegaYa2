<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyPortalProfile;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CompanySellerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_only_opens_the_sales_routes(): void
    {
        [$counter] = $this->seller();
        $allowed = EnsureCompanyPortalProfile::SELLER_ROUTES;

        $this->actingAs($counter);
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() !== 'empresa' && ! str_starts_with($route->uri(), 'empresa/')) {
                continue;
            }
            $uri = '/'.preg_replace('/\{([^}?]+)\??\}/', '999999', $route->uri());
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $response = $this->call($method, $uri);
                $label = $method.' '.$uri;
                if (in_array($route->getName(), $allowed, true) && $method === 'GET') {
                    $response->assertOk($label);
                } elseif (in_array($route->getName(), $allowed, true)) {
                    $this->assertNotSame(403, $response->getStatusCode(), $label);
                } else {
                    $this->assertSame(403, $response->getStatusCode(), $label.' devolvió '.$response->getStatusCode());
                }
            }
        }
    }

    public function test_a_company_cannot_sell_another_companys_departure(): void
    {
        [$counter, $organization] = $this->seller();
        $other = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Otra SAC', 'ruc' => '20999999009',
            'email' => 'otra-venta@test.pe', 'status' => 'active', 'modality' => 'fluvial',
        ]);
        $origin = Port::create(['name' => 'Origen ajeno', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::create(['name' => 'Destino ajeno', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::create(['organization_id' => $other->id, 'name' => 'Ajena', 'registration_number' => 'AJ-1', 'vessel_type' => 'lancha', 'seat_capacity' => 4, 'status' => 'ready']);
        $seat = VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1A', 'deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1]);
        $route = TransportRoute::create(['organization_id' => $other->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'AJ-1']);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now(),
            'fare' => 80, 'status' => 'scheduled', 'is_published' => true,
        ]);

        $this->actingAs($counter)->post(route('company.pos.store'), [
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'passenger_name' => 'Intruso',
            'passenger_age' => 30,
            'phone' => '999999999',
        ])->assertForbidden();
        $this->assertDatabaseMissing('reservations', ['route_departure_id' => $departure->id]);
        $this->assertNotSame($organization->id, $other->id);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function seller(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Vendedor SAC', 'ruc' => '20999999008',
            'email' => 'vendedor@test.pe', 'status' => 'active', 'modality' => 'fluvial',
        ]);
        $counter = User::factory()->create();
        $counter->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $organization->id]);
        $counter->organizations()->attach($organization->id, ['status' => 'active']);

        return [$counter, $organization];
    }
}
