<?php

namespace Tests\Feature;

use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Services\CashDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CargoTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_public_page_shows_status_and_hides_money_and_phones(): void
    {
        [$seller, $organization] = $this->staff();
        $departure = $this->departure($organization, 1, 'Nave Seguimiento');
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'route_departure_id' => $departure->id,
        ]))->assertRedirect();
        $shipment = CargoShipment::query()->firstOrFail();
        $shipment->events()->update(['note' => 'entregado a Ana Ríos, doc 87654321']);

        $this->get(route('cargo.track', ['codigo' => $shipment->code]))
            ->assertOk()
            ->assertSee($shipment->code);

        $this->post(route('cargo.track.lookup'), [
            'code' => $shipment->code,
            'document' => '44556677',
        ])->assertOk()
            ->assertSee('Iquitos')
            ->assertSee('Nauta')
            ->assertSee('Nave Seguimiento')
            ->assertSee(__('cargo.status_received'))
            ->assertSee(__('tracking.event_received'))
            ->assertSee('****6677')
            ->assertSee('****5678')
            ->assertDontSee('44556677')
            ->assertDontSee('12345678')
            ->assertDontSee('065600100')
            ->assertDontSee('065600200')
            ->assertDontSee('28.00')
            ->assertDontSee('25.00')
            ->assertDontSee('87654321')
            ->assertDontSee('Ana Ríos');

        CargoShipment::query()->create([
            'code' => 'ENC-20261116-0001',
            'sender_name' => 'Antigua',
            'sender_document' => '11112222',
            'sender_phone' => '065111111',
            'recipient_name' => 'Vieja',
            'recipient_document' => '33334444',
            'recipient_phone' => '065222222',
            'description' => 'Sobre',
            'package_count' => 1,
            'declared_weight_kg' => 1,
            'amount' => 9,
            'payment_status' => 'paid',
            'status' => 'received',
            'received_at' => now(),
        ]);
        $this->post(route('cargo.track.lookup'), [
            'code' => 'ENC-20261116-0001',
            'document' => '11112222',
        ])->assertOk()->assertSee(__('cargo.status_received'))->assertDontSee('9.00')->assertDontSee('065111111');
    }

    public function test_a_wrong_document_and_a_wrong_code_use_the_same_message(): void
    {
        [$seller, $organization] = $this->staff();
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel())->assertRedirect();
        $code = CargoShipment::query()->firstOrFail()->code;

        $this->from(route('cargo.track'))
            ->followingRedirects()
            ->post(route('cargo.track.lookup'), ['code' => $code, 'document' => '00000000'])
            ->assertOk()
            ->assertSee(__('tracking.miss'))
            ->assertDontSee('065600100')
            ->assertDontSee('28.00')
            ->assertDontSee('44556677');

        $this->from(route('cargo.track'))
            ->followingRedirects()
            ->post(route('cargo.track.lookup'), ['code' => 'ENC-NONE-20261116-0001', 'document' => '44556677'])
            ->assertOk()
            ->assertSee(__('tracking.miss'))
            ->assertDontSee('28.00');
    }

    public function test_lookup_attempts_are_limited_per_ip(): void
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->post(route('cargo.track.lookup'), [
                'code' => 'ENC-NONE-20261116-0001',
                'document' => '00000000',
            ])->assertRedirect();
        }

        $this->post(route('cargo.track.lookup'), [
            'code' => 'ENC-NONE-20261116-0001',
            'document' => '00000000',
        ])->assertStatus(429);
    }

    public function test_five_failed_lookups_hide_the_parcel_until_the_hour_passes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 20:00:00'));
        [$seller, $organization] = $this->staff();
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel())->assertRedirect();
        $code = CargoShipment::query()->firstOrFail()->code;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('cargo.track'))->post(route('cargo.track.lookup'), [
                'code' => $code,
                'document' => '00000000',
            ])->assertRedirect();
        }

        $this->from(route('cargo.track'))
            ->followingRedirects()
            ->post(route('cargo.track.lookup'), ['code' => $code, 'document' => '44556677'])
            ->assertOk()
            ->assertSee(__('tracking.miss'))
            ->assertDontSee('****6677');

        Carbon::setTestNow(Carbon::parse('2026-11-16 21:00:00'));
        $this->post(route('cargo.track.lookup'), [
            'code' => $code,
            'document' => '44556677',
        ])->assertOk()->assertSee('****6677')->assertDontSee('87654321');
    }

    public function test_a_vessel_without_capacity_accepts_the_parcel_and_warns(): void
    {
        [$seller, $organization] = $this->staff();
        $departure = $this->departure($organization, null, 'Nave Sin Tope');
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'route_departure_id' => $departure->id,
        ]))->assertRedirect();

        $this->actingAs($seller)->get(route('company.parcels.index'))
            ->assertOk()
            ->assertSee(__('cargo.no_capacity'));
        $this->assertSame(1, CargoShipment::query()->where('route_departure_id', $departure->id)->count());
    }

    public function test_the_admin_lists_active_vessels_without_cargo_capacity(): void
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Flota Aviso',
            'ruc' => '20631000999',
            'email' => 'flota@test.pe',
            'status' => 'active',
            'short_code' => 'AVIS',
        ]);
        Vessel::create([
            'organization_id' => $organization->id,
            'name' => 'Lista Sin Tope',
            'registration_number' => 'SIN-TOPE',
            'vessel_type' => 'lancha',
            'seat_capacity' => 10,
            'status' => 'ready',
        ]);
        Vessel::create([
            'organization_id' => $organization->id,
            'name' => 'Borrador Sin Tope',
            'registration_number' => 'BOR-TOPE',
            'vessel_type' => 'lancha',
            'seat_capacity' => 10,
            'status' => 'draft',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador'])->id);

        $html = $this->actingAs($admin)->get(route('admin.vessels.index'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'Lista Sin Tope'));
        $this->assertSame(1, substr_count($html, 'Borrador Sin Tope'));
        $this->assertStringContainsString('Sin capacidad de carga', $html);
    }

    public function test_parcels_waiting_for_a_new_departure_are_highlighted_and_filterable(): void
    {
        [$seller, $organization] = $this->staff();
        $departure = $this->departure($organization, 1, 'Nave Reasigna');
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'route_departure_id' => $departure->id,
            'sender_document' => '44556677',
        ]))->assertRedirect();
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'sender_document' => '99887766',
        ]))->assertRedirect();
        $loose = CargoShipment::query()->where('sender_document', '44556677')->firstOrFail();
        $kept = CargoShipment::query()->where('sender_document', '99887766')->firstOrFail();
        $departure->update(['status' => 'cancelled']);

        $this->actingAs($seller)->get(route('company.parcels.index'))
            ->assertOk()
            ->assertSee('is-loose')
            ->assertSee($loose->code)
            ->assertSee($kept->code);

        $this->actingAs($seller)->get(route('company.parcels.index', ['reasignar' => 1]))
            ->assertOk()
            ->assertSee($loose->code)
            ->assertDontSee($kept->code);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function parcel(array $extra = []): array
    {
        return array_merge([
            'sender_name' => 'Rosa Quispe',
            'sender_document' => '44556677',
            'sender_phone' => '065600100',
            'recipient_name' => 'Luis Paredes',
            'recipient_document' => '12345678',
            'recipient_phone' => '065600200',
            'description' => 'Caja de fariña',
            'package_count' => 1,
            'declared_weight_kg' => 6,
            'length_cm' => 40,
            'width_cm' => 30,
            'height_cm' => 20,
            'amount' => 28,
            'method' => 'cash',
            'cash_tendered' => 30,
        ], $extra);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function staff(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Seguimiento SAC',
            'ruc' => '20631000888',
            'email' => 'seguimiento@test.pe',
            'status' => 'active',
            'short_code' => 'SEGU',
        ]);
        $seller = User::factory()->create();
        $seller->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $organization->id]);
        $seller->organizations()->attach($organization->id, ['status' => 'active']);
        app(CashDesk::class)->open($seller, $organization->id, null, 0);

        return [$seller, $organization];
    }

    private function departure(Organization $organization, ?float $tons, string $name): RouteDeparture
    {
        $origin = Port::create(['name' => 'Origen '.$name, 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Destino '.$name, 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'registration_number' => 'TRK-'.substr(md5($name), 0, 8),
            'vessel_type' => 'lancha',
            'seat_capacity' => 10,
            'cargo_capacity_tons' => $tons,
            'status' => 'ready',
        ]);
        $route = TransportRoute::create([
            'organization_id' => $organization->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
        ]);

        return RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addHours(5),
            'fare' => 80,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
    }
}
