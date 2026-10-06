<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\AircraftSeat;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_renders_and_exports_air_reservations(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['code' => 'super_admin', 'name' => 'Administrador principal']);
        $admin->roles()->attach($role);
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Aero Loreto SAC', 'ruc' => '20123456789', 'email' => 'aero@example.com', 'status' => 'active']);
        $aircraft = Aircraft::create(['organization_id' => $company->id, 'name' => 'Cessna 208B', 'registration_number' => 'OB-REPORT', 'seat_capacity' => 1, 'status' => 'ready']);
        $seat = AircraftSeat::create(['aircraft_id' => $aircraft->id, 'code' => '1A', 'row_position' => 1, 'column_position' => 1, 'is_available' => true]);
        $route = AirRoute::create(['organization_id' => $company->id, 'origin_city' => 'Iquitos', 'destination_city' => 'Contamana', 'code' => 'AIR-REPORT']);
        $departure = AirDeparture::create(['air_route_id' => $route->id, 'aircraft_id' => $aircraft->id, 'departure_at' => now()->addDay(), 'fare' => 280, 'status' => 'scheduled']);
        $reservation = Reservation::create(['air_departure_id' => $departure->id, 'code' => 'NY-REPORT', 'contact_name' => 'Ana Ruiz', 'contact_email' => 'ana@example.com', 'contact_phone' => '999888777', 'total_amount' => 280, 'status' => 'confirmed']);
        ReservationSeat::create(['reservation_id' => $reservation->id, 'air_departure_id' => $departure->id, 'aircraft_seat_id' => $seat->id, 'passenger_name' => 'Ana Ruiz', 'document_number' => '44556677']);

        $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()->assertSee('Iquitos')->assertSee('Contamana')->assertSee('Cessna 208B')->assertSee('Aéreo');
        $export = $this->actingAs($admin)->get(route('admin.reports.export'));
        $export->assertOk()->assertDownload();
        $this->assertStringContainsString('Aéreo', $export->streamedContent());
    }
}
