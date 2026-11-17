<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CargoShipment;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Services\CashDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CargoDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_codes_are_unique_and_follow_the_lima_day(): void
    {
        $this->travelTo('2026-11-16 04:30:00');
        [$seller, $organization] = $this->staff('Carga Uno', '20631000001');
        [$otherSeller, $other] = $this->staff('Carga Dos', '20631000002');

        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel())->assertRedirect();
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel(['sender_document' => '44556678']))->assertRedirect();
        $this->assertSame(
            ['ENC-'.$organization->short_code.'-20261115-0001', 'ENC-'.$organization->short_code.'-20261115-0002'],
            CargoShipment::query()->where('organization_id', $organization->id)->orderBy('code')->pluck('code')->all(),
        );

        $this->travelTo('2026-11-16 05:30:00');
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel(['sender_document' => '44556679']))->assertRedirect();
        $this->actingAs($otherSeller)->post(route('company.parcels.store'), $this->parcel())->assertRedirect();

        $this->assertDatabaseHas('cargo_shipments', ['organization_id' => $organization->id, 'code' => 'ENC-'.$organization->short_code.'-20261116-0001']);
        $this->assertDatabaseHas('cargo_shipments', ['organization_id' => $other->id, 'code' => 'ENC-'.$other->short_code.'-20261116-0001']);
    }

    public function test_a_departure_cannot_take_more_weight_than_the_vessel(): void
    {
        [$seller, $organization] = $this->staff('Carga Tope', '20631000003');
        $departure = $this->departure($organization, 0.01);

        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'route_departure_id' => $departure->id,
            'declared_weight_kg' => 6,
        ]))->assertRedirect();
        $this->actingAs($seller)->from(route('company.parcels.index'))->post(route('company.parcels.store'), $this->parcel([
            'route_departure_id' => $departure->id,
            'declared_weight_kg' => 6,
            'sender_document' => '99887766',
        ]))->assertRedirect()->assertSessionHasErrors('route_departure_id');

        $this->assertSame(1, CargoShipment::query()->where('route_departure_id', $departure->id)->count());
    }

    public function test_a_cancelled_departure_returns_parcels_without_losing_the_payment(): void
    {
        [$seller, $organization] = $this->staff('Carga Salida', '20631000004');
        $site = $this->site($organization);
        $departure = $this->departure($organization, 1);
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'route_departure_id' => $departure->id,
            'site_id' => $site->id,
            'cash_tendered' => 40,
        ]))->assertRedirect();
        $shipment = CargoShipment::query()->firstOrFail();
        $payment = Payment::query()->where('cargo_shipment_id', $shipment->id)->firstOrFail();
        $this->assertSame('40.00', $payment->cash_tendered);
        $this->assertSame('15.00', $payment->cash_change);
        $this->assertSame($seller->id, $payment->collected_by_user_id);
        $this->assertSame($site->id, $payment->organization_site_id);
        $this->assertSame('cargo', $payment->purpose);
        $this->assertSame('0.00', $payment->commission_amount);

        $departure->update(['status' => 'cancelled']);

        $shipment->refresh();
        $this->assertSame('received', $shipment->status);
        $this->assertNull($shipment->route_departure_id);
        $this->assertTrue($shipment->needs_reassignment);
        $this->assertSame('confirmed', $payment->fresh()->status);
        $this->assertSame('25.00', $payment->fresh()->amount);
    }

    public function test_delivery_requires_the_collector_document(): void
    {
        [$seller, $organization] = $this->staff('Carga Entrega', '20631000005');
        $departure = $this->departure($organization, 1);
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'route_departure_id' => $departure->id,
        ]))->assertRedirect();
        $shipment = CargoShipment::query()->firstOrFail();
        $this->actingAs($seller)->patch(route('company.parcels.board', $shipment))->assertRedirect();
        $this->actingAs($seller)->from(route('company.parcels.index'))->patch(route('company.parcels.deliver', $shipment), [])
            ->assertRedirect()
            ->assertSessionHasErrors('collector_document');
        $this->assertSame('boarded', $shipment->fresh()->status);
    }

    public function test_recipient_payment_stays_pending_until_delivery(): void
    {
        [$seller, $organization] = $this->staff('Carga Destino', '20631000006');
        $departure = $this->departure($organization, 1);
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel([
            'method' => 'recipient',
            'route_departure_id' => $departure->id,
        ]))->assertRedirect();
        $shipment = CargoShipment::query()->firstOrFail();
        $this->assertSame('pending', $shipment->payment_status);
        $this->assertDatabaseMissing('payments', ['cargo_shipment_id' => $shipment->id]);

        $this->actingAs($seller)->patch(route('company.parcels.board', $shipment))->assertRedirect();
        $this->actingAs($seller)->patch(route('company.parcels.deliver', $shipment), [
            'collector_document' => '87654321',
            'collector_name' => 'Ana Ríos',
            'method' => 'cash',
            'cash_tendered' => 25,
        ])->assertRedirect();

        $shipment->refresh();
        $this->assertSame('delivered', $shipment->status);
        $this->assertSame('paid', $shipment->payment_status);
        $this->assertSame('87654321', $shipment->collector_document);
        $paid = Payment::query()->where('cargo_shipment_id', $shipment->id)->firstOrFail();
        $this->assertSame('cash', $paid->method);
        $this->assertSame('25.00', $paid->cash_tendered);
        $this->assertSame('0.00', $paid->cash_change);
        $this->assertSame($seller->id, $paid->collected_by_user_id);
        $this->assertNotNull($shipment->delivered_by_user_id);
        $this->assertNotNull($shipment->boarded_at);
    }

    public function test_yape_without_an_operation_number_is_rejected(): void
    {
        [$seller] = $this->staff('Carga Yape', '20631000007');

        $this->actingAs($seller)->from(route('company.parcels.index'))->post(route('company.parcels.store'), $this->parcel([
            'method' => 'yape',
        ]))->assertRedirect()->assertSessionHasErrors('operation_number');
        $this->assertSame(0, CargoShipment::query()->count());
    }

    public function test_another_company_cannot_see_or_change_the_parcel(): void
    {
        [$seller, $organization] = $this->staff('Carga Propia', '20631000008');
        [$intruder] = $this->staff('Carga Ajena', '20631000009');
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel())->assertRedirect();
        $shipment = CargoShipment::query()->firstOrFail();
        $this->flushSession();

        $this->actingAs($intruder)->get(route('company.parcels.index'))->assertOk()->assertDontSee($shipment->code);
        $this->actingAs($intruder)->patch(route('company.parcels.board', $shipment))->assertForbidden();
        $this->actingAs($intruder)->get(route('company.parcels.guide', $shipment))->assertForbidden();
        $this->assertSame($organization->id, $shipment->fresh()->organization_id);
    }

    public function test_an_agency_seller_cannot_open_the_module(): void
    {
        [$seller, $organization] = $this->staff('Carga Agencia', '20631000010');
        $agency = Agency::create([
            'organization_id' => $organization->id,
            'name' => 'Agencia Carga',
            'ruc' => '20111222333',
            'contact_name' => 'Nilo',
            'phone' => '065111222',
            'status' => 'active',
        ]);
        $seller->update(['agency_id' => $agency->id]);

        $this->actingAs($seller)->get(route('company.parcels.index'))->assertForbidden();
        $this->actingAs($seller)->post(route('company.parcels.store'), $this->parcel())->assertForbidden();
        $this->actingAs($seller)->get(route('company.freight.index'))->assertForbidden();
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
            'amount' => 25,
            'method' => 'cash',
            'cash_tendered' => 30,
        ], $extra);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function staff(string $name, string $ruc): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'ruc' => $ruc,
            'email' => strtolower(str_replace(' ', '', $name)).'@test.pe',
            'status' => 'active',
            'short_code' => $this->sigla($ruc),
        ]);
        $seller = User::factory()->create();
        $seller->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $organization->id]);
        $seller->organizations()->attach($organization->id, ['status' => 'active']);
        app(CashDesk::class)->open($seller, $organization->id, null, 0);

        return [$seller, $organization];
    }

    private function site(Organization $organization): OrganizationSite
    {
        return OrganizationSite::create([
            'organization_id' => $organization->id,
            'name' => 'Muelle de prueba',
            'kind' => 'dock',
            'address' => 'Malecón',
            'city' => 'Iquitos',
            'is_active' => true,
        ]);
    }

    private function departure(Organization $organization, float $tons): RouteDeparture
    {
        $origin = Port::create(['name' => 'Origen '.$organization->ruc, 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Destino '.$organization->ruc, 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::create([
            'organization_id' => $organization->id,
            'name' => 'Nave '.$organization->ruc,
            'registration_number' => 'CG-'.$organization->ruc,
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

    private function sigla(string $ruc): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $n = (int) substr($ruc, -4);

        return $alphabet[intdiv($n, 676) % 26].$alphabet[intdiv($n, 26) % 26].$alphabet[$n % 26].'X';
    }
}
