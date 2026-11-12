<?php

namespace Tests\Feature;

use App\Mail\DepartureNoticeMail;
use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\DepartureSeries;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Support\DepartureSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StageFourDeparturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_series_uses_lima_time_and_skips_overlapping_dates(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();
        Mail::fake();

        $this->actingAs($admin)->post(route('company.departures.store'), [
            'schedule_kind' => 'series',
            'master_route_id' => $master->id,
            'vessel_id' => $vessel->id,
            'departure_date' => '2026-11-16',
            'departure_time' => '08:00',
            'fare' => '80',
            'weekdays' => [1],
            'starts_on' => '2026-11-16',
            'ends_on' => '2026-11-23',
        ])->assertRedirect(route('company.departures.index'));

        $this->assertSame(2, RouteDeparture::query()->count());
        $first = RouteDeparture::query()->orderBy('departure_at')->first();
        $this->assertSame('08:00', $first->departure_at->timezone('America/Lima')->format('H:i'));
        $this->assertSame('2026-11-16', $first->departure_at->timezone('America/Lima')->toDateString());

        $this->actingAs($admin)->get(route('company.departures.index'))
            ->assertOk()
            ->assertSee('08:00')
            ->assertSee('El viajero web pagará');

        $this->actingAs($admin)->post(route('company.departures.store'), [
            'schedule_kind' => 'series',
            'master_route_id' => $master->id,
            'vessel_id' => $vessel->id,
            'departure_date' => '2026-11-16',
            'departure_time' => '09:00',
            'fare' => '80',
            'weekdays' => [1],
            'starts_on' => '2026-11-16',
            'ends_on' => '2026-11-23',
        ])->assertRedirect();

        $this->assertSame(2, RouteDeparture::query()->count());
        $this->actingAs($admin)->get(route('company.departures.index'))
            ->assertSee('2 fechas no se crearon')
            ->assertSee('16/11/2026 09:00')
            ->assertSee('23/11/2026 09:00');
    }

    public function test_rescheduling_notifies_passengers_without_marking_whatsapp_as_sent(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();
        $route = TransportRoute::create([
            'organization_id' => $company->id,
            'master_route_id' => $master->id,
            'origin_port_id' => $master->origin_port_id,
            'destination_port_id' => $master->destination_port_id,
            'estimated_duration_minutes' => 150,
            'status' => 'active',
        ]);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDays(3)->setTime(8, 0),
            'fare' => 80,
            'status' => 'scheduled',
        ]);
        Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-AVISO',
            'contact_name' => 'Ana',
            'contact_email' => 'ana@aviso.test',
            'contact_phone' => '999111222',
            'total_amount' => 80,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        Mail::fake();

        $this->actingAs($admin)->patch(route('company.departures.contingency', $departure), [
            'action' => 'reschedule',
            'departure_at' => now()->addDays(5)->timezone('America/Lima')->setTime(11, 0)->format('Y-m-d H:i:s'),
            'reason' => 'El muelle cambió de horario',
        ])->assertRedirect();

        Mail::assertSent(DepartureNoticeMail::class);
        $this->assertDatabaseHas('departure_notices', ['channel' => 'email', 'status' => 'sent']);
        $this->assertDatabaseHas('departure_notices', ['channel' => 'whatsapp', 'status' => 'prepared', 'sent_at' => null]);
    }

    public function test_deleting_a_series_keeps_departures_that_already_have_sales(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();

        $this->actingAs($admin)->post(route('company.departures.store'), [
            'schedule_kind' => 'series',
            'master_route_id' => $master->id,
            'vessel_id' => $vessel->id,
            'departure_date' => '2026-11-16',
            'departure_time' => '08:00',
            'fare' => '80',
            'weekdays' => [1],
            'starts_on' => '2026-11-16',
            'ends_on' => '2026-11-23',
        ])->assertRedirect();

        $sold = RouteDeparture::query()->orderBy('departure_at')->first();
        $kept = $sold->departure_at->format('Y-m-d H:i:s');
        Reservation::create([
            'route_departure_id' => $sold->id,
            'code' => 'NY-SERIE',
            'contact_name' => 'Luis',
            'contact_email' => 'luis@serie.test',
            'contact_phone' => '999111222',
            'total_amount' => 80,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('company.departures.series.destroy', $sold->departure_series_id))
            ->assertRedirect();

        $this->assertSame($kept, $sold->fresh()->departure_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, RouteDeparture::query()->count());
        $this->assertSame('retired', DepartureSeries::query()->find($sold->departure_series_id)->status);
    }

    public function test_editing_a_series_does_not_move_a_departure_that_already_has_sales(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();
        $this->actingAs($admin)->post(route('company.departures.store'), [
            'schedule_kind' => 'series',
            'master_route_id' => $master->id,
            'vessel_id' => $vessel->id,
            'departure_date' => '2026-11-16',
            'departure_time' => '08:00',
            'fare' => '80',
            'weekdays' => [1],
            'starts_on' => '2026-11-16',
            'ends_on' => '2026-11-23',
        ])->assertRedirect();
        $sold = RouteDeparture::query()->orderBy('departure_at')->first();
        $kept = $sold->departure_at->format('Y-m-d H:i:s');
        Reservation::create([
            'route_departure_id' => $sold->id,
            'code' => 'NY-EDITA',
            'contact_name' => 'Luis',
            'contact_email' => 'luis@edita.test',
            'contact_phone' => '999111222',
            'total_amount' => 80,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('company.departures.series.update', $sold->departure_series_id), [
            'master_route_id' => $master->id,
            'vessel_id' => $vessel->id,
            'departure_date' => '2026-11-16',
            'departure_time' => '10:00',
            'fare' => '90',
            'weekdays' => [1],
            'starts_on' => '2026-11-16',
            'ends_on' => '2026-11-23',
        ])->assertRedirect();

        $this->assertSame($kept, $sold->fresh()->departure_at->format('Y-m-d H:i:s'));
        $this->assertSame('10:00', RouteDeparture::query()->orderByDesc('departure_at')->first()->departure_at->timezone('America/Lima')->format('H:i'));
    }

    public function test_an_aircraft_cannot_overlap_its_own_flight(): void
    {
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Aire', 'ruc' => '20641000041', 'email' => 'aire@test.pe', 'status' => 'active', 'modality' => 'aereo']);
        $aircraft = Aircraft::create(['organization_id' => $company->id, 'name' => 'OB-001', 'registration_number' => 'OB-001', 'aircraft_kind' => 'light_aircraft', 'seat_capacity' => 4, 'status' => 'ready']);
        $route = AirRoute::create(['organization_id' => $company->id, 'origin_city' => 'Iquitos', 'destination_city' => 'Nauta', 'estimated_duration_minutes' => 90, 'status' => 'active']);
        $start = DepartureSchedule::at('2026-11-16', '08:00');
        AirDeparture::create(['air_route_id' => $route->id, 'aircraft_id' => $aircraft->id, 'departure_at' => $start, 'fare' => 100, 'status' => 'scheduled']);

        $this->assertTrue(DepartureSchedule::conflicts($aircraft, DepartureSchedule::at('2026-11-16', '09:00'), 90)->isNotEmpty());
        $this->assertTrue(DepartureSchedule::conflicts($aircraft, DepartureSchedule::at('2026-11-16', '09:30'), 90)->isEmpty());
    }

    /**
     * @return array{0: User, 1: Organization, 2: Vessel, 3: MasterRoute}
     */
    private function riverCompany(): array
    {
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $master = MasterRoute::create([
            'code' => 'TRM-LIMA', 'modality' => 'fluvial', 'origin_city' => 'Iquitos', 'destination_city' => 'Nauta',
            'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
            'estimated_duration_text' => '2 h 30 min', 'estimated_duration_minutes' => 150, 'status' => 'active',
        ]);
        $company = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Río Lima', 'commercial_name' => 'Río Lima',
            'ruc' => '20641000040', 'email' => 'lima@test.pe', 'status' => 'active', 'modality' => 'fluvial',
            'commission_rate' => 8,
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']), ['organization_id' => $company->id]);
        $admin->organizations()->attach($company->id, ['status' => 'active']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Lancha Lima', 'registration_number' => 'LIM-1', 'vessel_type' => 'lancha', 'seat_capacity' => 4, 'status' => 'ready']);
        foreach (range(1, 4) as $number) {
            VesselSeat::create(['vessel_id' => $vessel->id, 'code' => (string) $number, 'deck' => 'principal', 'row_position' => $number, 'column_position' => 1]);
        }

        return [$admin, $company, $vessel, $master];
    }
}
