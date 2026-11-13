<?php

namespace Tests\Feature;

use App\Models\CrewMember;
use App\Models\DepartureAssignment;
use App\Models\DepartureIncident;
use App\Models\IncidentPhoto;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Support\CrewRoster;
use App\Support\DepartureSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StageFourCrewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_departure_is_published_without_a_captain(): void
    {
        [$admin, , $vessel, $master] = $this->riverCompany();

        $this->actingAs($admin)->post(route('company.departures.store'), [
            'master_route_id' => $master->id,
            'vessel_id' => $vessel->id,
            'departure_date' => '2026-12-01',
            'departure_time' => '08:00',
            'fare' => '80',
        ])->assertRedirect(route('company.departures.index'));

        $departure = RouteDeparture::query()->first();
        $this->assertNotNull($departure);
        $this->assertTrue($departure->is_published);
        $this->assertSame(0, DepartureAssignment::query()->count());
    }

    public function test_the_same_person_cannot_board_two_departures_that_overlap(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();
        $other = $this->vessel($company, 'LIM-2');
        $route = $this->route($company, $master);
        $captain = $this->captain($company, '2027-12-01');
        $first = $this->departure($route, $vessel, '2026-12-07', '08:00');
        $touching = $this->departure($route, $other, '2026-12-07', '10:30');
        $crossing = $this->departure($route, $other, '2026-12-07', '10:00');

        $this->actingAs($admin)->post(route('company.crew.assign'), [
            'departure_id' => $first->id,
            'captain_id' => $captain->id,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('company.crew.assign'), [
            'departure_id' => $touching->id,
            'captain_id' => $captain->id,
        ])->assertRedirect();
        $this->assertSame(2, DepartureAssignment::query()->count());

        $this->actingAs($admin)->post(route('company.crew.assign'), [
            'departure_id' => $crossing->id,
            'captain_id' => $captain->id,
        ])->assertSessionHasErrors([
            'captain_id' => 'Esa persona ya está en una salida que se cruza con este horario.',
        ]);
        $this->assertSame(2, DepartureAssignment::query()->count());
    }

    public function test_rescheduling_into_another_assignment_is_rejected(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();
        $other = $this->vessel($company, 'LIM-2');
        $route = $this->route($company, $master);
        $captain = $this->captain($company, '2027-12-01');
        $first = $this->departure($route, $vessel, '2026-12-07', '08:00');
        $second = $this->departure($route, $other, '2026-12-07', '14:00');
        CrewRoster::sync($first, $captain->id, []);
        CrewRoster::sync($second, $captain->id, []);

        $this->actingAs($admin)->patch(route('company.departures.contingency', $first), [
            'action' => 'reschedule',
            'departure_at' => '2026-12-07 14:30:00',
            'reason' => 'El muelle cambió de horario',
        ])->assertSessionHasErrors([
            'departure_at' => 'Esa persona ya está en una salida que se cruza con este horario.',
        ]);
        $this->assertSame('08:00', $first->fresh()->departure_at->timezone('America/Lima')->format('H:i'));
    }

    public function test_an_expired_license_cannot_be_assigned(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();
        $route = $this->route($company, $master);
        $captain = $this->captain($company, '2026-12-01');
        $departure = $this->departure($route, $vessel, '2026-12-07', '08:00');

        $this->actingAs($admin)->post(route('company.crew.assign'), [
            'departure_id' => $departure->id,
            'captain_id' => $captain->id,
        ])->assertSessionHasErrors([
            'captain_id' => 'Esa persona no tiene una licencia vigente para la fecha de la salida.',
        ]);
        $this->assertSame(0, DepartureAssignment::query()->count());
    }

    public function test_boarding_stays_closed_until_a_captain_has_a_valid_license(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();
        $route = $this->route($company, $master);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now(),
            'fare' => 80,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
        $seat = VesselSeat::query()->where('vessel_id', $vessel->id)->first();
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-CREW',
            'contact_name' => 'Ana',
            'contact_email' => 'ana@crew.test',
            'contact_phone' => '999000111',
            'total_amount' => 80,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        $reservationSeat = ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Ana',
            'document_number' => '12345678',
        ]);
        $ticket = Ticket::create([
            'reservation_seat_id' => $reservationSeat->id,
            'code' => 'BOL-CREW',
            'boarding_token' => 'TOKENCREW',
            'status' => 'confirmed',
            'issued_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('company.counter.index'))
            ->assertOk()
            ->assertSee(CrewRoster::BOARDING_BLOCK)
            ->assertDontSee('>Embarcar<', false);
        $this->get(route('company.boarding.show', $departure))
            ->assertOk()
            ->assertSee(CrewRoster::BOARDING_BLOCK)
            ->assertDontSee('Marcar a bordo');
        $this->patch(route('company.boarding.toggle', [$departure, $ticket]))
            ->assertStatus(422);

        $captain = $this->captain($company, now()->addYear()->toDateString());
        CrewRoster::sync($departure, $captain->id, []);

        $this->get(route('company.counter.index'))->assertOk()->assertSee('Embarcar');
        $this->get(route('company.boarding.show', $departure))->assertOk()->assertSee('Marcar a bordo');
        $this->patch(route('company.boarding.toggle', [$departure, $ticket]))->assertRedirect();
        $this->assertSame('boarded', $ticket->fresh()->status);
    }

    public function test_only_the_company_admin_and_the_superadmin_see_the_document_and_phone(): void
    {
        [$admin, $company] = $this->riverCompany();
        $captain = $this->captain($company, '2027-12-01');
        $captain->update(['phone' => '999111222']);
        $counter = User::factory()->create();
        $counter->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $company->id]);
        $counter->organizations()->attach($company->id, ['status' => 'active']);
        $seller = User::factory()->create();
        $seller->roles()->attach(Role::firstOrCreate(['code' => 'company_seller'], ['name' => 'Vendedor de empresa'])->id, ['organization_id' => $company->id]);
        $seller->organizations()->attach($company->id, ['status' => 'active']);
        $super = User::factory()->create();
        $super->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadmin'])->id);

        $this->actingAs($admin)->get(route('company.crew.index'))
            ->assertOk()
            ->assertSee($captain->document_number)
            ->assertSee('999111222');
        $this->actingAs($counter)->get(route('company.crew.index'))
            ->assertForbidden()
            ->assertDontSee($captain->document_number);
        $this->actingAs($seller)->get(route('company.crew.index'))
            ->assertForbidden()
            ->assertDontSee($captain->document_number);
        $this->actingAs($counter)->get(route('company.incidents.index'))->assertForbidden();
        $this->actingAs($super)->get(route('admin.companies.show', $company))
            ->assertOk()
            ->assertSee($captain->document_number)
            ->assertSee('999111222');
    }

    public function test_incident_photos_stay_on_the_private_disk(): void
    {
        [$admin, $company, $vessel, $master] = $this->riverCompany();
        $route = $this->route($company, $master);
        $departure = $this->departure($route, $vessel, '2026-12-07', '08:00');
        $other = User::factory()->create();
        $otherCompany = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Otra', 'ruc' => '20641000099',
            'email' => 'otra@test.pe', 'status' => 'active', 'modality' => 'fluvial',
        ]);
        $other->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa'])->id, ['organization_id' => $otherCompany->id]);
        $other->organizations()->attach($otherCompany->id, ['status' => 'active']);
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs($admin)->post(route('company.incidents.store'), [
            'departure_id' => $departure->id,
            'kind' => 'breakdown',
            'severity' => 'high',
            'description' => 'Se apagó el motor a mitad del río.',
            'action' => 'record',
            'photos' => [UploadedFile::fake()->image('motor.jpg')],
        ])->assertRedirect(route('company.incidents.index'));

        $photo = IncidentPhoto::query()->first();
        $this->assertNotNull($photo);
        Storage::disk('local')->assertExists($photo->path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(1, DepartureIncident::query()->count());
        $this->actingAs($other)->get(route('company.incidents.photos.show', $photo))->assertNotFound();
        $this->actingAs($admin)->get(route('company.incidents.photos.show', $photo))->assertOk();
    }

    /**
     * @return array{0: User, 1: Organization, 2: Vessel, 3: MasterRoute}
     */
    private function riverCompany(): array
    {
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $master = MasterRoute::create([
            'code' => 'TRM-CREW', 'modality' => 'fluvial', 'origin_city' => 'Iquitos', 'destination_city' => 'Nauta',
            'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
            'estimated_duration_text' => '2 h 30 min', 'estimated_duration_minutes' => 150, 'status' => 'active',
        ]);
        $company = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Río Tripulación', 'commercial_name' => 'Río Tripulación',
            'ruc' => '20641000077', 'email' => 'crew@test.pe', 'status' => 'active', 'modality' => 'fluvial',
            'commission_rate' => 8,
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa'])->id, ['organization_id' => $company->id]);
        $admin->organizations()->attach($company->id, ['status' => 'active']);

        return [$admin, $company, $this->vessel($company, 'LIM-1'), $master];
    }

    private function vessel(Organization $company, string $registration): Vessel
    {
        $vessel = Vessel::create([
            'organization_id' => $company->id, 'name' => 'Lancha '.$registration, 'registration_number' => $registration,
            'vessel_type' => 'lancha', 'seat_capacity' => 4, 'status' => 'ready',
        ]);
        foreach (range(1, 4) as $number) {
            VesselSeat::create(['vessel_id' => $vessel->id, 'code' => $registration.'-'.$number, 'deck' => 'principal', 'row_position' => $number, 'column_position' => 1]);
        }

        return $vessel;
    }

    private function route(Organization $company, MasterRoute $master): TransportRoute
    {
        return TransportRoute::create([
            'organization_id' => $company->id,
            'master_route_id' => $master->id,
            'origin_port_id' => $master->origin_port_id,
            'destination_port_id' => $master->destination_port_id,
            'estimated_duration_minutes' => 150,
            'status' => 'active',
        ]);
    }

    private function captain(Organization $company, string $expiresOn): CrewMember
    {
        return CrewMember::create([
            'organization_id' => $company->id,
            'name' => 'Juan Pérez',
            'document_number' => '44556677',
            'role' => 'captain',
            'license_number' => 'LIC-1',
            'license_expires_on' => $expiresOn,
            'status' => 'active',
        ]);
    }

    private function departure(TransportRoute $route, Vessel $vessel, string $date, string $time): RouteDeparture
    {
        return RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => DepartureSchedule::at($date, $time),
            'fare' => 80,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
    }
}
