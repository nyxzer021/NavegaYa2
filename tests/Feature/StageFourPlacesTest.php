<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Port;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Support\FleetPlan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageFourPlacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_whole_cabin_counts_its_beds_for_capacity_and_life_vests(): void
    {
        [$admin, $company] = $this->companyAdmin();
        $vessel = $this->vessel($company, 20, 20);
        $this->drawSeats($vessel, 16);

        $this->actingAs($admin)->post(route('company.vessels.cabins.store', $vessel), [
            'cabin_code' => 'C-01',
            'bed_count' => 4,
        ])->assertRedirect();

        $vessel->unsetRelation('seats');
        $this->assertSame(20, FleetPlan::people($vessel));
        $this->assertTrue(FleetPlan::isComplete($vessel));
        $this->assertDatabaseHas('vessel_seats', [
            'vessel_id' => $vessel->id,
            'code' => 'C-01',
            'space_type' => 'cabin_bed',
            'cabin_sold_as' => 'whole',
            'bed_count' => 4,
        ]);

        $this->actingAs($admin)->post(route('company.fleet.vessels.submit-review', $vessel))->assertRedirect();
        $this->assertSame('pending_review', $vessel->fresh()->status);

        $tight = $this->vessel($company, 20, 19, 'FLE-VEST');
        $this->drawSeats($tight, 16);
        $this->actingAs($admin)->from(route('company.vessels.seats.edit', $tight))->post(route('company.vessels.cabins.store', $tight), [
            'cabin_code' => 'C-02',
            'bed_count' => 4,
        ])->assertRedirect(route('company.vessels.seats.edit', $tight))->assertSessionHasErrors('quantity');
        $this->assertDatabaseMissing('vessel_seats', ['code' => 'C-02']);

        $over = $this->vessel($company, 20, null, 'FLE-OVER');
        $this->drawSeats($over, 17);
        $this->actingAs($admin)->from(route('company.vessels.seats.edit', $over))->post(route('company.vessels.cabins.store', $over), [
            'cabin_code' => 'C-03',
            'bed_count' => 4,
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(17, FleetPlan::people($over->fresh()));
    }

    public function test_hammocks_are_numbered_and_two_sales_cannot_take_the_same_one(): void
    {
        [$admin, $company] = $this->companyAdmin('20641000022', 'hamacas@test.pe');
        $vessel = $this->vessel($company, 4, null, 'FLE-HAM');
        $this->actingAs($admin)->post(route('company.vessels.hammocks.store', $vessel), ['quantity' => 2])->assertRedirect();
        $this->assertDatabaseHas('vessel_seats', ['vessel_id' => $vessel->id, 'code' => 'H-01', 'space_type' => 'hammock']);
        $this->assertDatabaseHas('vessel_seats', ['vessel_id' => $vessel->id, 'code' => 'H-02', 'space_type' => 'hammock']);

        $departure = $this->departure($company, $vessel);
        $payload = [
            'place' => 'hammock',
            'contact_name' => 'Ana Río',
            'contact_email' => 'ana@test.pe',
            'contact_phone' => '999111222',
            'contact_document' => '12345678',
        ];
        $this->post(route('bookings.store', $departure), $payload)->assertRedirect(route('cart.index'));
        $this->post(route('bookings.store', $departure), array_merge($payload, [
            'contact_name' => 'Luis Río',
            'contact_email' => 'luis@test.pe',
            'contact_document' => '87654321',
        ]))->assertRedirect(route('cart.index'));
        $this->from(route('bookings.create', $departure))->post(route('bookings.store', $departure), array_merge($payload, [
            'contact_name' => 'Nilo Río',
            'contact_email' => 'nilo@test.pe',
        ]))->assertSessionHasErrors('place');

        $sold = ReservationSeat::query()->where('route_departure_id', $departure->id)->pluck('vessel_seat_id');
        $this->assertCount(2, $sold);
        $this->assertCount(2, $sold->unique());
    }

    public function test_the_same_cabin_cannot_be_sold_twice_and_existing_seats_stay_seats(): void
    {
        [$admin, $company] = $this->companyAdmin('20641000033', 'cabina@test.pe');
        $vessel = $this->vessel($company, 8, null, 'FLE-CAB');
        $this->actingAs($admin)->post(route('company.vessels.cabins.store', $vessel), [
            'cabin_code' => 'c-01',
            'bed_count' => 2,
        ])->assertRedirect();
        $cabin = VesselSeat::query()->where('code', 'C-01')->firstOrFail();
        $this->assertSame('seat', VesselSeat::create([
            'vessel_id' => $vessel->id,
            'code' => '1A',
            'deck' => 'principal',
            'row_position' => 1,
            'column_position' => 1,
        ])->fresh()->space_type);

        $departure = $this->departure($company, $vessel);
        $payload = [
            'contact_name' => 'Ana Río',
            'contact_email' => 'ana@test.pe',
            'contact_phone' => '999111222',
            'seats' => [$cabin->id],
            'passengers' => [['name' => 'Ana Río', 'document' => '12345678']],
        ];
        $this->post(route('bookings.store', $departure), $payload)->assertRedirect();
        $this->from(route('bookings.create', $departure))->post(route('bookings.store', $departure), array_merge($payload, [
            'contact_name' => 'Luis Río',
            'contact_email' => 'luis@test.pe',
            'passengers' => [['name' => 'Luis Río', 'document' => '87654321']],
        ]))->assertSessionHasErrors('seats');
        $this->assertSame(1, ReservationSeat::query()->where('vessel_seat_id', $cabin->id)->count());

        $this->expectException(QueryException::class);
        VesselSeat::create([
            'vessel_id' => $vessel->id,
            'code' => 'C-MAL',
            'deck' => 'principal',
            'space_type' => 'cabin_bed',
            'cabin_sold_as' => 'whole',
        ]);
    }

    public function test_the_old_missing_seat_sentence_is_not_shown_with_the_plan_warning(): void
    {
        [$admin, $company] = $this->companyAdmin('20641000044', 'frase@test.pe');
        $this->vessel($company, 12, null, 'FLE-FRASE');

        $this->actingAs($admin)->get(route('company.fleet.index'))
            ->assertOk()
            ->assertSee('Falta configurar el plano (0 de 12)')
            ->assertDontSee('asientos por configurar');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function companyAdmin(string $ruc = '20641000011', string $email = 'plazas@test.pe'): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Plazas Etapa',
            'commercial_name' => 'Plazas Etapa',
            'ruc' => $ruc,
            'email' => $email,
            'status' => 'active',
            'modality' => 'fluvial',
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function vessel(Organization $company, int $capacity, ?int $vests, string $registration = 'FLE-PLAZA'): Vessel
    {
        return Vessel::create([
            'organization_id' => $company->id,
            'name' => 'Lancha '.$registration,
            'registration_number' => $registration,
            'vessel_type' => 'lancha',
            'seat_capacity' => $capacity,
            'life_vest_count' => $vests,
            'status' => 'draft',
        ]);
    }

    private function drawSeats(Vessel $vessel, int $count): void
    {
        for ($seat = 1; $seat <= $count; $seat++) {
            $vessel->seats()->create([
                'code' => 'S'.$seat,
                'deck' => 'principal',
                'row_position' => $seat,
                'column_position' => 1,
                'is_available' => true,
            ]);
        }
    }

    private function departure(Organization $company, Vessel $vessel): RouteDeparture
    {
        $origin = Port::create(['name' => 'Puerto A '.$company->id, 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto B '.$company->id, 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $route = TransportRoute::create([
            'organization_id' => $company->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'code' => 'PLZ-'.$company->id,
        ]);

        return RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDay(),
            'fare' => 50,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
    }
}
