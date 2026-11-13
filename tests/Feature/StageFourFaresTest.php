<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\RouteFare;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Services\MarketplaceSales;
use App\Support\FareTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageFourFaresTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_fares_screen_shows_baggage_and_links_to_the_policy_editor(): void
    {
        [$admin, $company] = $this->company();
        $company->update(['baggage_hand_kg' => 10, 'baggage_hold_kg' => 20, 'baggage_air_kg' => 8, 'cargo_threshold_kg' => 50]);

        $this->actingAs($admin)->get(route('company.fares.index'))
            ->assertOk()
            ->assertSee('Tarifas y descuentos')
            ->assertSee('Mano 10 kg')
            ->assertSee('bodega 20 kg')
            ->assertSee('aéreo 8 kg')
            ->assertSee('carga desde 50 kg')
            ->assertSee('Políticas de viaje')
            ->assertDontSee('name="baggage_hand_kg"', false);
    }

    public function test_overlapping_seasons_of_the_same_class_are_rejected(): void
    {
        [$admin, $company, $route] = $this->company();
        $first = [
            'route' => 'river-'.$route->id,
            'space_type' => 'seat',
            'passenger_type' => 'adult',
            'amount' => 100,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-20',
        ];
        $this->actingAs($admin)->post(route('company.fares.store'), $first)->assertRedirect();
        $this->actingAs($admin)->from(route('company.fares.index'))->post(route('company.fares.store'), [
            ...$first,
            'starts_on' => '2026-12-20',
            'ends_on' => '2026-12-31',
        ])->assertSessionHasErrors('ends_on');
        $this->actingAs($admin)->from(route('company.fares.index'))->post(route('company.fares.store'), [
            ...$first,
            'starts_on' => null,
            'ends_on' => null,
        ])->assertSessionHasErrors('ends_on');
        $this->assertSame(1, RouteFare::query()->count());
    }

    public function test_a_class_without_a_fare_is_not_sold_and_other_passengers_use_the_adult_fare(): void
    {
        [, $company, $route, $departure, $seat] = $this->trip();
        $departure->vessel->seats()->create(['code' => 'H-01', 'deck' => 'principal', 'space_type' => 'hammock', 'is_available' => true]);

        $this->get(route('bookings.create', $departure))
            ->assertOk()
            ->assertSee('Hamaca: No disponible')
            ->assertSee('No disponible');
        $this->from(route('bookings.create', $departure))->post(route('bookings.store', $departure), [
            'place' => 'hammock',
            'contact_name' => 'Ana Río',
            'contact_email' => 'ana@test.pe',
            'contact_phone' => '999111222',
        ])->assertSessionHasErrors('place');

        $this->assertSame(100.0, FareTable::price($route, 'seat', 'adult', $departure->departure_at, (float) $departure->fare));
        $card = view('components.ui.departure-card', [
            'departure' => $departure->fresh()->load(['transportRoute.originPort', 'transportRoute.destinationPort', 'vessel.organization', 'vessel.seats', 'reservations.seats']),
        ])->render();
        $this->assertStringContainsString('Hamaca: No disponible', $card);
        $this->assertStringContainsString('S/ 108.00', $card);

        RouteFare::create(['organization_id' => $company->id, 'transport_route_id' => $route->id, 'space_type' => 'hammock', 'passenger_type' => 'adult', 'amount' => 80]);
        $this->assertSame(80.0, FareTable::price($route, 'hammock', 'child', $departure->departure_at, (float) $departure->fare));
        $this->assertSame(80.0, FareTable::price($route, 'hammock', 'student', $departure->departure_at, null));
        $this->assertSame(80.0, FareTable::price($route, 'hammock', 'senior', $departure->departure_at, null));
        $this->assertNull(FareTable::price($route, 'hammock', 'infant', $departure->departure_at, (float) $departure->fare));
        $this->assertNull(FareTable::price($route, 'cabin_bed', 'adult', $departure->departure_at, (float) $departure->fare));
    }

    public function test_ten_percent_of_one_hundred_charges_the_web_fee_on_ninety_and_nothing_at_the_counter(): void
    {
        [$admin, $company, $route, $departure, $seat] = $this->trip();
        $this->actingAs($admin)->post(route('company.fares.store'), [
            'route' => 'river-'.$route->id,
            'space_type' => 'seat',
            'passenger_type' => 'adult',
            'amount' => 100,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('company.discounts.store'), [
            'name' => 'Temporada',
            'kind' => 'percent',
            'value' => 10,
            'channels' => ['web'],
        ])->assertRedirect();
        $discount = Discount::query()->firstOrFail();
        auth()->logout();

        $this->post(route('bookings.store', $departure), [
            'contact_name' => 'Ana Torres',
            'contact_email' => 'ana@test.pe',
            'contact_phone' => '999888777',
            'contact_document' => '12345678',
            'seats' => [$seat->id],
            'passengers' => [['name' => 'Ana Torres', 'document' => '12345678']],
            'discount_id' => $discount->id,
        ])->assertRedirect();

        $reservation = Reservation::query()->firstOrFail();
        $this->assertSame('100.00', $reservation->base_amount);
        $this->assertSame('10.00', $reservation->discount_amount);
        $this->assertSame('90.00', $reservation->total_amount);
        $this->assertSame(1, $discount->fresh()->redeemed_count);

        config()->set('payments.sandbox_enabled', true);
        $this->post(route('payments.sandbox-confirm', $reservation->code), ['method' => 'yape'])->assertRedirect();
        $reservation->refresh();
        $this->assertSame('7.20', $reservation->service_fee_amount);
        $this->assertSame('97.20', $reservation->total_amount);

        $counter = $reservation->replicate();
        $counter->sales_channel = 'counter';
        $quoted = app(MarketplaceSales::class)->quote($counter);
        $this->assertSame(90.0, $quoted['charged_amount']);
        $this->assertSame(0.0, $quoted['service_fee_amount']);

        $floor = FareTable::net(100, new Discount(['kind' => 'amount', 'value' => 250]));
        $this->assertSame(0.0, $floor['net']);
        $this->assertSame(100.0, $floor['discount']);

        $reservation->update(['status' => 'refunded']);
        $this->assertSame(0, $discount->fresh()->redeemed_count);

        $reservation->refresh()->update(['discount_held' => true, 'status' => 'confirmed']);
        Discount::query()->whereKey($discount->id)->update(['redeemed_count' => 1]);
        Payment::query()->where('reservation_id', $reservation->id)->firstOrFail()->update(['status' => 'refunded']);
        $this->assertSame(0, $discount->fresh()->redeemed_count);
        $this->assertFalse($reservation->fresh()->discount_held);
    }

    public function test_an_infant_needs_one_adult_a_fare_and_a_life_vest_and_appears_on_the_manifest(): void
    {
        [$admin, $company, $route, $departure, $seat] = $this->trip(2);
        RouteFare::create(['organization_id' => $company->id, 'transport_route_id' => $route->id, 'space_type' => 'seat', 'passenger_type' => 'infant', 'amount' => 0]);
        $payload = [
            'contact_name' => 'Ana Torres',
            'contact_email' => 'ana@test.pe',
            'contact_phone' => '999888777',
            'seats' => [$seat->id],
            'passengers' => [['name' => 'Ana Torres', 'document' => '12345678']],
            'infants' => [
                ['name' => 'Nilo Torres', 'document' => '70000003'],
                ['name' => 'Rosa Torres', 'document' => '70000004'],
            ],
        ];
        $this->from(route('bookings.create', $departure))->post(route('bookings.store', $departure), $payload)->assertSessionHasErrors('seats');

        unset($payload['infants'][1]);
        $this->post(route('bookings.store', $departure), $payload)->assertRedirect();
        $this->assertDatabaseHas('reservation_seats', ['passenger_name' => 'Nilo Torres', 'passenger_type' => 'infant', 'vessel_seat_id' => null]);

        $this->actingAs($admin)->get(route('company.departures.manifest', $departure))
            ->assertOk()
            ->assertSee('Nilo Torres')
            ->assertSee('Infante');

        $second = $departure->vessel->seats()->create(['code' => '1B', 'deck' => 'principal', 'row_position' => 1, 'column_position' => 2, 'is_available' => true]);
        $this->from(route('bookings.create', $departure))->post(route('bookings.store', $departure), [
            'contact_name' => 'Luis Río',
            'contact_email' => 'luis@test.pe',
            'contact_phone' => '999111222',
            'seats' => [$second->id],
            'passengers' => [['name' => 'Luis Río', 'document' => '87654321']],
        ])->assertSessionHasErrors('seats');
    }

    /**
     * @return array{0: User, 1: Organization, 2?: TransportRoute, 3?: RouteDeparture, 4?: VesselSeat}
     */
    private function company(): array
    {
        $company = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Tarifas SAC',
            'commercial_name' => 'Tarifas',
            'ruc' => '20641000999',
            'email' => 'tarifas@test.pe',
            'status' => 'active',
            'modality' => 'fluvial',
            'commission_rate' => 8,
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $company->id]);
        $admin->organizations()->attach($company->id, ['status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Tarifas', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto Destino', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $route = TransportRoute::create([
            'organization_id' => $company->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'code' => 'TAR-1',
            'status' => 'active',
        ]);

        return [$admin, $company, $route];
    }

    /**
     * @return array{0: User, 1: Organization, 2: TransportRoute, 3: RouteDeparture, 4: VesselSeat}
     */
    private function trip(?int $vests = null): array
    {
        [$admin, $company, $route] = $this->company();
        $vessel = Vessel::create([
            'organization_id' => $company->id,
            'name' => 'Lancha Tarifas',
            'registration_number' => 'TAR-01',
            'vessel_type' => 'lancha',
            'seat_capacity' => 4,
            'life_vest_count' => $vests,
            'status' => 'ready',
        ]);
        $seat = VesselSeat::create([
            'vessel_id' => $vessel->id,
            'code' => '1A',
            'deck' => 'principal',
            'row_position' => 1,
            'column_position' => 1,
            'is_available' => true,
        ]);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDay(),
            'fare' => 100,
            'status' => 'scheduled',
            'is_published' => true,
        ]);

        return [$admin, $company, $route, $departure, $seat];
    }
}
