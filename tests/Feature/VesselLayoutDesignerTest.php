<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VesselLayoutDesignerTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_draws_seats_and_boat_elements_and_the_passenger_sees_the_same_plan(): void
    {
        [$admin, $company] = $this->companyAdmin();
        $vessel = $this->vessel($company, 10);

        $this->actingAs($admin)->put(route('company.vessels.layout.save', $vessel), [
            'deck' => 'principal', 'rows' => 3, 'columns' => 3,
            'cells' => json_encode([
                ['r' => 1, 'c' => 2, 't' => 'helm'],
                ['r' => 2, 'c' => 1, 't' => 'seat'], ['r' => 2, 'c' => 3, 't' => 'seat', 'b' => true],
                ['r' => 3, 'c' => 1, 't' => 'seat'], ['r' => 3, 'c' => 3, 't' => 'bathroom'],
            ]),
        ])->assertRedirect(route('company.vessels.seats.edit', ['vessel' => $vessel, 'deck' => 'principal']))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vessel_seats', ['vessel_id' => $vessel->id, 'code' => '1A', 'row_position' => 2, 'column_position' => 1, 'is_available' => true]);
        $this->assertDatabaseHas('vessel_seats', ['vessel_id' => $vessel->id, 'code' => '1B', 'row_position' => 2, 'column_position' => 3, 'is_available' => false]);
        $this->assertDatabaseHas('vessel_seats', ['vessel_id' => $vessel->id, 'code' => '2A', 'row_position' => 3, 'column_position' => 1]);
        $this->assertSame(['1-2' => 'helm', '3-3' => 'bathroom'], $vessel->fresh()->layout['principal']['cells']);

        $vessel->update(['status' => 'ready']);
        $departure = $this->departure($company, $vessel);
        $this->get(route('bookings.create', $departure))->assertOk()
            ->assertSee('aria-label="Cabina de mando"', false)
            ->assertSee('aria-label="Baño"', false)
            ->assertSee('data-code="1A"', false);
    }

    public function test_a_seat_sold_for_a_future_departure_cannot_be_removed_from_the_plan(): void
    {
        [$admin, $company] = $this->companyAdmin();
        $vessel = $this->vessel($company, 10);
        $sold = $vessel->seats()->create(['code' => '1A', 'deck' => 'principal', 'row_position' => 1, 'column_position' => 1, 'is_available' => true]);
        $vessel->seats()->create(['code' => '1B', 'deck' => 'principal', 'row_position' => 1, 'column_position' => 2, 'is_available' => true]);
        $departure = $this->departure($company, $vessel);
        $reservation = Reservation::create(['route_departure_id' => $departure->id, 'code' => 'RES-PLANO', 'contact_name' => 'Ana', 'contact_email' => 'ana@test.pe', 'contact_phone' => '999', 'total_amount' => 50, 'status' => 'confirmed']);
        ReservationSeat::create(['reservation_id' => $reservation->id, 'route_departure_id' => $departure->id, 'vessel_seat_id' => $sold->id, 'passenger_name' => 'Ana', 'document_type' => 'DNI', 'document_number' => '12345678']);

        $this->actingAs($admin)->from(route('company.vessels.seats.edit', $vessel))->put(route('company.vessels.layout.save', $vessel), [
            'deck' => 'principal', 'rows' => 1, 'columns' => 2,
            'cells' => json_encode([['r' => 1, 'c' => 2, 't' => 'seat']]),
        ])->assertSessionHasErrors('layout');
        $this->assertDatabaseHas('vessel_seats', ['id' => $sold->id]);

        $this->actingAs($admin)->put(route('company.vessels.layout.save', $vessel), [
            'deck' => 'principal', 'rows' => 1, 'columns' => 3,
            'cells' => json_encode([['r' => 1, 'c' => 1, 't' => 'seat', 'b' => true], ['r' => 1, 'c' => 3, 't' => 'seat']]),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vessel_seats', ['id' => $sold->id, 'code' => '1A', 'is_available' => false]);
    }

    public function test_the_plan_cannot_exceed_capacity_and_is_locked_while_in_review(): void
    {
        [$admin, $company] = $this->companyAdmin();
        $vessel = $this->vessel($company, 2);
        $threeSeats = json_encode([['r' => 1, 'c' => 1, 't' => 'seat'], ['r' => 1, 'c' => 2, 't' => 'seat'], ['r' => 1, 'c' => 3, 't' => 'seat']]);

        $this->actingAs($admin)->from(route('company.vessels.seats.edit', $vessel))->put(route('company.vessels.layout.save', $vessel), [
            'deck' => 'principal', 'rows' => 1, 'columns' => 3, 'cells' => $threeSeats,
        ])->assertSessionHasErrors('layout');
        $this->assertSame(0, $vessel->seats()->count());

        $vessel->update(['status' => 'pending_review']);
        $this->actingAs($admin)->put(route('company.vessels.layout.save', $vessel), [
            'deck' => 'principal', 'rows' => 1, 'columns' => 1, 'cells' => json_encode([['r' => 1, 'c' => 1, 't' => 'seat']]),
        ])->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function companyAdmin(): array
    {
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => 'Plano Fluvial', 'commercial_name' => 'Plano Fluvial', 'ruc' => '20641000099', 'email' => 'plano@test.pe', 'status' => 'active', 'modality' => 'fluvial']);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function vessel(Organization $company, int $capacity): Vessel
    {
        return Vessel::create(['organization_id' => $company->id, 'name' => 'Lancha Plano', 'registration_number' => 'PLANO-1', 'vessel_type' => 'lancha', 'seat_capacity' => $capacity, 'life_vest_count' => $capacity, 'status' => 'draft']);
    }

    private function departure(Organization $company, Vessel $vessel): RouteDeparture
    {
        $origin = Port::create(['name' => 'Puerto Plano A', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto Plano B', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'PLANO-'.$company->id, 'status' => 'active']);

        return RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 50, 'status' => 'scheduled', 'is_published' => true]);
    }
}
