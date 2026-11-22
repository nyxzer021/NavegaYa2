<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\CargoShipment;
use App\Models\CashSession;
use App\Models\Discount;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\RouteFare;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CompanyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(17, 0));
    }

    public function test_each_listed_action_leaves_a_readable_record(): void
    {
        [$admin, $organization] = $this->company('Auditoría Río', '20632000001');
        $seller = $this->member($organization, 'company_counter', 'Rosa Quispe');
        $departure = $this->departure($organization, 'Nave Auditoría');
        $seat = $this->seat($departure, '1A');
        $agencySeat = $this->seat($departure, '1B', 2);
        $this->hammock($departure, 'H1');
        $agency = Agency::create([
            'organization_id' => $organization->id, 'name' => 'Agencia Auditoría', 'ruc' => '20111000001',
            'contact_name' => 'Luz Paredes', 'phone' => '065600111', 'status' => 'active', 'max_surcharge_percent' => 0,
        ]);
        $agencySeller = $this->member($organization, 'company_counter', 'Vendedor Agencia');
        $agencySeller->forceFill(['agency_id' => $agency->id])->save();

        $this->actingAs($seller)->post(route('company.cash-close.open'), ['opening_float' => 40])->assertRedirect();
        $this->actingAs($seller)->post(route('company.pos.store'), [
            'departure_id' => $departure->id, 'seat_id' => $seat->id, 'first_name' => 'Ana', 'last_name' => 'Ríos',
            'document_type' => 'DNI', 'document_number' => '44556677', 'passenger_type' => 'adult',
            'method' => 'cash', 'cash_tendered' => 100,
        ])->assertRedirect();
        $reservation = Reservation::query()->firstOrFail();
        $this->actingAs($seller)->post(route('company.pos.void', $reservation), ['reason' => 'El pasajero desistió'])->assertRedirect();
        $sessionId = CashSession::query()->where('user_id', $seller->id)->value('id');
        $this->actingAs($seller)->post(route('company.cash-close.close', $sessionId), [
            'counted_cash' => 35, 'closing_note' => 'Faltan cinco soles',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('company.agencies.lots.store', $agency), [
            'departure_id' => $departure->id, 'seats' => [$agencySeat->id], 'unit_price' => 80, 'payment_status' => 'pending',
        ])->assertRedirect();
        $lot = $agency->lots()->firstOrFail();
        $this->actingAs($admin)->post(route('company.agency-commissions.payments.store', $lot), [
            'amount' => '80.00', 'paid_on' => now()->toDateString(), 'method' => 'Transferencia', 'operation_number' => 'OP-LOTE',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($agencySeller)->post(route('company.pos.store'), [
            'departure_id' => $departure->id, 'seat_id' => $agencySeat->id, 'first_name' => 'Luis', 'last_name' => 'Pinedo',
            'document_type' => 'DNI', 'document_number' => '87654321', 'passenger_type' => 'adult',
            'sale_price' => 95, 'method' => 'cash', 'cash_tendered' => 95,
        ])->assertRedirect();
        $hammock = VesselSeat::query()->where('code', 'H1')->firstOrFail();
        $this->actingAs($admin)->post(route('company.agencies.lots.store', $agency), [
            'departure_id' => $departure->id, 'hammocks' => 1, 'unit_price' => 30, 'payment_status' => 'pending',
        ])->assertRedirect();
        $hammockLot = $agency->lots()->whereKeyNot($lot->id)->firstOrFail();
        $this->actingAs($admin)->post(route('company.agencies.lots.release', $hammockLot), [
            'seats' => [$hammock->id], 'note' => 'La agencia no las usó',
        ])->assertRedirect();

        $parcel = CargoShipment::query()->create([
            'organization_id' => $organization->id, 'code' => 'ENC-AUD-0001', 'sender_name' => 'Mario',
            'sender_document' => '11112222', 'sender_phone' => '999111222', 'recipient_name' => 'Nora',
            'recipient_document' => '33334444', 'recipient_phone' => '999333444', 'description' => 'Caja',
            'package_count' => 1, 'declared_weight_kg' => 4, 'amount' => 25, 'payment_status' => 'paid',
            'payment_method' => 'cash', 'status' => 'boarded',
        ]);
        $this->actingAs($seller)->patch(route('company.parcels.deliver', $parcel), [
            'collector_name' => 'Nora', 'collector_document' => '33334444',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('company.fares.store'), [
            'route' => 'river-'.$departure->transport_route_id, 'space_type' => 'seat', 'passenger_type' => 'adult', 'amount' => 90,
        ])->assertRedirect();
        $fare = RouteFare::query()->firstOrFail();
        $this->actingAs($admin)->delete(route('company.fares.destroy', $fare))->assertRedirect();
        $this->actingAs($admin)->post(route('company.discounts.store'), [
            'name' => 'Niño del muelle', 'kind' => 'percent', 'value' => 10, 'channels' => ['counter'],
        ])->assertRedirect();
        $discount = Discount::query()->firstOrFail();
        $this->actingAs($admin)->delete(route('company.discounts.destroy', $discount))->assertRedirect();

        $later = $this->departure($organization, 'Nave Reprogramada');
        $this->actingAs($admin)->patch(route('company.departures.status', $departure), ['status' => 'cancelled'])->assertRedirect();
        $this->actingAs($admin)->patch(route('company.departures.contingency', $later), [
            'action' => 'reschedule', 'reason' => 'El río creció',
            'departure_at' => now()->addDays(4)->timezone('America/Lima')->format('Y-m-d H:i'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('company.staff.store'), [
            'name' => 'Nilo Operador', 'email' => 'nilo.auditoria@test.pe', 'profile' => 'operator',
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('company.profile.update'), ['commercial_name' => 'Río Auditoría'])->assertRedirect();
        $this->actingAs($admin)->patch(route('company.details.update'), [
            'legal_name' => 'Auditoría Río SAC', 'commercial_name' => 'Río Auditoría', 'address' => 'Malecón 1',
            'base_city' => 'Iquitos', 'contact_name' => 'Ada Admin',
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('company.finance.bank'), [
            'bank_name' => 'Banco de la Selva', 'bank_account' => '1234567890', 'bank_cci' => '12345678901234567890',
        ])->assertRedirect();

        $events = [
            'sale.counter', 'sale.agency', 'counter_sale_voided', 'cash.opened', 'cash.closed',
            'parcel.delivered', 'lot.created', 'lot_payment.recorded', 'lot.released', 'fare.saved', 'fare.removed',
            'discount.saved', 'discount.removed', 'departure.cancelled', 'departure.rescheduled',
            'staff.invited', 'profile.updated', 'details.updated', 'bank.updated',
        ];
        foreach ($events as $event) {
            $this->assertTrue(
                AuditLog::query()->where('organization_id', $organization->id)->where('event', $event)->exists(),
                'Falta el evento '.$event,
            );
        }
        $this->assertSame(0, AuditLog::query()->where('changes', 'like', '%1234567890%')->count());

        $this->actingAs($admin)->get(route('company.audit.index'))
            ->assertOk()
            ->assertSee('Rosa Quispe anuló la venta del muelle del boleto '.$reservation->code)
            ->assertSee('El pasajero desistió')
            ->assertSee('diferencia S/ -5.00')
            ->assertSee('Nilo Operador')
            ->assertDontSee('sale.counter', false)
            ->assertDontSee('counter_sale_voided', false)
            ->assertDontSee('staff.invited', false);
    }

    public function test_a_company_cannot_read_another_and_the_csv_respects_filters(): void
    {
        [$admin, $organization] = $this->company('Empresa Visible', '20632000002');
        $seller = $this->member($organization, 'company_counter', 'Cajero Visible');
        $finance = $this->member($organization, 'company_finance', 'Finanzas Visible');
        $operator = $this->member($organization, 'company_operator', 'Operador Visible');
        [$other] = $this->company('Empresa Ajena', '20632000003');

        $this->actingAs($seller)->post(route('company.cash-close.open'), ['opening_float' => 15])->assertRedirect();
        $this->actingAs($admin)->patch(route('company.profile.update'), ['commercial_name' => 'Visible Pública'])->assertRedirect();

        $this->actingAs($finance)->get(route('company.audit.index'))
            ->assertOk()
            ->assertSee('Auditoría')
            ->assertSee('Cajero Visible abrió la caja')
            ->assertSee('actualizó la ficha pública');
        $this->actingAs($other)->get(route('company.audit.index'))
            ->assertOk()
            ->assertDontSee('Cajero Visible')
            ->assertDontSee('Visible Pública');
        $this->actingAs($seller)->get(route('company.audit.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('company.audit.export'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.audit.index'))->assertForbidden();

        $csv = $this->actingAs($admin)->get(route('company.audit.export', ['empleado' => $seller->id, 'tipo' => 'caja']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $body);
        $this->assertStringContainsString('Cajero Visible', $body);
        $this->assertStringNotContainsString('ficha pública', $body);
        $this->assertStringNotContainsString('cash.opened', $body);

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! is_string($name) || ! str_starts_with($name, 'company.audit.')) {
                continue;
            }
            $this->assertSame([], array_values(array_diff($route->methods(), ['GET', 'HEAD'])));
        }

        $super = User::factory()->create();
        $super->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super'])->id);
        $this->actingAs($super)->get(route('admin.audit.index', ['organization' => $organization->id]))
            ->assertOk()
            ->assertSee('Cajero Visible')
            ->assertSee(__('audit.all_companies'));
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $name, string $ruc): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => $name, 'commercial_name' => $name,
            'ruc' => $ruc, 'email' => strtolower(str_replace(' ', '', $name)).'@test.pe', 'status' => 'active', 'modality' => 'fluvial',
        ]);
        $admin = User::factory()->create(['name' => 'Ada '.$name]);
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function member(Organization $organization, string $code, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['code' => $code], ['name' => $name])->id, ['organization_id' => $organization->id]);
        $user->organizations()->attach($organization->id, ['status' => 'active']);

        return $user;
    }

    private function departure(Organization $organization, string $vesselName): RouteDeparture
    {
        $origin = Port::query()->create(['name' => 'Puerto '.$vesselName, 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Destino '.$vesselName, 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => $vesselName, 'registration_number' => 'MAT-'.$vesselName,
            'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready',
        ]);
        $master = MasterRoute::query()->create([
            'code' => 'AUD-'.substr(md5($vesselName), 0, 8), 'modality' => 'fluvial', 'origin_city' => 'Iquitos',
            'destination_city' => 'Nauta', 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id, 'master_route_id' => $master->id,
            'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
        ]);

        return RouteDeparture::query()->create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addHours(5),
            'fare' => 95, 'status' => 'scheduled', 'is_published' => true,
        ]);
    }

    private function seat(RouteDeparture $departure, string $code, int $column = 1): VesselSeat
    {
        return $departure->vessel->seats()->create([
            'code' => $code, 'row_position' => 1, 'column_position' => $column,
            'deck' => 'principal', 'space_type' => 'seat', 'is_available' => true,
        ]);
    }

    private function hammock(RouteDeparture $departure, string $code): VesselSeat
    {
        return $departure->vessel->seats()->create([
            'code' => $code, 'row_position' => 2, 'column_position' => 1,
            'deck' => 'principal', 'space_type' => 'hammock', 'is_available' => true,
        ]);
    }
}
