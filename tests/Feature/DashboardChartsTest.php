<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\SubscriptionPlan;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_charts_use_real_series_and_seats_from_departures_in_the_period(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['code' => 'super_admin', 'name' => 'Super administrador']));

        $this->seatOnDeparture('HOY', now(), 'confirmed', 'yape', now());
        $this->seatOnDeparture('AYER', now()->subDay(), 'confirmed', 'card', now()->subDay());
        SubscriptionPlan::create([
            'code' => 'loreto-chart',
            'name' => 'Plan Loreto',
            'monthly_price' => 300,
            'currency' => 'PEN',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Soles cobrados en el período')
            ->assertSee('1 de 4 asientos en salidas del período')
            ->assertSee('fluvialCapacity: [1, 3]', false)
            ->assertSee('"Yape"', false)
            ->assertSee('labels: d.planNames', false)
            ->assertSee('"Plan Loreto"', false)
            ->assertDontSee("['Esencial','Destacado','Cobertura total']", false);
    }

    private function seatOnDeparture(string $code, $departureAt, string $status, string $method, $paidAt): void
    {
        $company = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Rio '.$code,
            'ruc' => '20'.random_int(100000000, 999999999),
            'email' => strtolower($code).'@test.pe',
            'status' => 'active',
            'commission_rate' => 7.5,
        ]);
        $origin = Port::create(['name' => 'Origen '.$code, 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Destino '.$code, 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create([
            'organization_id' => $company->id,
            'name' => 'Lancha '.$code,
            'registration_number' => $code,
            'vessel_type' => 'lancha',
            'seat_capacity' => $code === 'HOY' ? 4 : 10,
            'status' => 'ready',
        ]);
        $seat = VesselSeat::create([
            'vessel_id' => $vessel->id,
            'code' => '1A',
            'deck' => 'principal',
            'seat_class' => 'standard',
            'row_position' => 1,
            'column_position' => 1,
        ]);
        $route = TransportRoute::create([
            'organization_id' => $company->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'code' => $code,
        ]);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => $departureAt,
            'fare' => 100,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'RES-'.$code,
            'contact_name' => 'Ana',
            'contact_email' => strtolower($code).'@viajero.test',
            'contact_phone' => '999000111',
            'total_amount' => 100,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);
        ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Ana',
            'document_number' => '12345678',
        ]);
        Payment::create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => $method,
            'provider' => 'sandbox',
            'provider_reference' => 'PAY-'.$code,
            'amount' => 100,
            'commission_rate' => 7.5,
            'commission_amount' => 7.5,
            'operator_net' => 92.5,
            'status' => 'confirmed',
            'paid_at' => $paidAt,
        ]);
    }
}
