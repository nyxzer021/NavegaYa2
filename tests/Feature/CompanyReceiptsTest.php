<?php

namespace Tests\Feature;

use App\Contracts\ElectronicInvoicing;
use App\Models\CargoShipment;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Port;
use App\Models\ReceiptSequence;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Services\CashDesk;
use App\Services\NullElectronicInvoicing;
use App\Services\PaymentReceipts;
use App\Support\DepartureSchedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyReceiptsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(15, 0));
        app()->setLocale('es');
    }

    public function test_two_sales_get_different_numbers_and_the_certificate_is_not_a_sunat_document(): void
    {
        [$admin, $organization] = $this->company('Río Comprobantes', '20674000001');
        $organization->update(['short_code' => 'RIO']);
        app(CashDesk::class)->open($admin, $organization->id, null, 0);
        $departure = $this->departure($organization);
        $firstSeat = $this->seat($departure, '1A', 1);
        $secondSeat = $this->seat($departure, '1B', 2);
        $year = (int) now()->timezone(DepartureSchedule::ZONE)->format('Y');
        $firstNumber = sprintf('CP-%d-%06d', $year, 1);
        $secondNumber = sprintf('CP-%d-%06d', $year, 2);
        $parcelNumber = sprintf('CP-%d-%06d', $year, 3);

        $this->actingAs($admin)->get(route('company.pos.index', ['departure' => $departure->id]))
            ->assertOk()
            ->assertSee('Necesita factura');

        $this->actingAs($admin)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->sale($departure, $firstSeat, [
            'needs_invoice' => 1,
            'buyer_ruc' => '2061234567',
            'buyer_legal_name' => 'Corta SAC',
        ]))->assertRedirect()->assertSessionHasErrors('buyer_ruc');
        $this->actingAs($admin)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->sale($departure, $firstSeat, [
            'needs_invoice' => 1,
            'buyer_ruc' => '2061234567A',
            'buyer_legal_name' => 'Letra SAC',
        ]))->assertRedirect()->assertSessionHasErrors('buyer_ruc');
        $this->actingAs($admin)->from(route('company.pos.index'))->post(route('company.pos.store'), $this->sale($departure, $firstSeat, [
            'needs_invoice' => 1,
            'buyer_ruc' => '20612345678',
        ]))->assertRedirect()->assertSessionHasErrors('buyer_legal_name');
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('payment_receipts', 0);

        $this->actingAs($admin)->post(route('company.pos.store'), $this->sale($departure, $firstSeat, [
            'first_name' => 'Nila',
            'last_name' => 'Vega',
            'needs_invoice' => 1,
            'buyer_ruc' => '20612345678',
            'buyer_legal_name' => '=Comercial Río SAC',
            'buyer_address' => 'Malecón 10',
        ]))->assertRedirect();
        $this->actingAs($admin)->post(route('company.pos.store'), $this->sale($departure, $secondSeat, [
            'first_name' => 'Omar',
            'last_name' => 'Díaz',
            'document_number' => '44556677',
        ]))->assertRedirect();
        $this->actingAs($admin)->post(route('company.parcels.store'), [
            'sender_name' => 'Rosa Quispe',
            'sender_document' => '12345678',
            'sender_phone' => '065600100',
            'recipient_name' => 'Teo Ramos',
            'recipient_document' => '87654321',
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
        ])->assertRedirect();

        $this->assertSame(
            [$firstNumber, $secondNumber, $parcelNumber],
            PaymentReceipt::query()->where('organization_id', $organization->id)->orderBy('sequence')->pluck('number')->all(),
        );
        $this->assertSame(3, ReceiptSequence::query()->where('organization_id', $organization->id)->value('last_number'));

        $invoiced = Reservation::query()->where('needs_invoice', true)->firstOrFail();
        $this->assertSame('RUC', $invoiced->buyer_document_type);
        $this->assertSame('20612345678', $invoiced->buyer_document_number);
        $this->assertSame('=Comercial Río SAC', $invoiced->buyer_legal_name);
        $this->assertSame('Malecón 10', $invoiced->buyer_address);
        $this->assertSame('95.00', $invoiced->total_amount);
        $this->assertSame('confirmed', $invoiced->payments()->firstOrFail()->status);
        $plain = Reservation::query()->where('contact_name', 'Omar Díaz')->firstOrFail();
        $this->assertFalse($plain->needs_invoice);
        $this->assertSame('DNI', $plain->buyer_document_type);
        $this->assertSame('44556677', $plain->buyer_document_number);
        $this->assertNull($plain->buyer_legal_name);

        $provider = app(ElectronicInvoicing::class);
        $this->assertInstanceOf(NullElectronicInvoicing::class, $provider);
        $this->assertFalse($provider->issue([
            'number' => $firstNumber,
            'amount' => 'S/ 95.00',
            'buyer_name' => '=Comercial Río SAC',
            'buyer_document' => 'RUC 20612345678',
        ])['issued']);
        $this->assertSame('No es un comprobante electrónico SUNAT', __('receipts.not_sunat'));

        $receipt = PaymentReceipt::query()->where('number', $firstNumber)->firstOrFail();
        $html = view('company.receipts.certificate', app(PaymentReceipts::class)->certificate($receipt))->render();
        $this->assertStringContainsString('No es un comprobante electrónico SUNAT', $html);
        $this->assertStringContainsString('Sin proveedor de facturación electrónica', $html);
        $this->assertStringContainsString('S/ 95.00', $html);
        $this->assertStringContainsString('=Comercial Río SAC', $html);
        $this->actingAs($admin)->get(route('company.receipts.certificate', $receipt))->assertOk();

        $ticket = Ticket::query()->whereHas('reservationSeat', fn ($seat) => $seat->where('reservation_id', $invoiced->id))->firstOrFail();
        $shipment = CargoShipment::query()->where('organization_id', $organization->id)->firstOrFail();
        $this->actingAs($admin)->get(route('company.receipts.index'))
            ->assertOk()
            ->assertSee('Comprobantes')
            ->assertSee('data-count="receipts">3', false)
            ->assertSee('data-count="tickets">S/ 190.00', false)
            ->assertSee('data-count="parcels">S/ 25.00', false)
            ->assertSee('data-count="invoices">1', false)
            ->assertSee($firstNumber)
            ->assertSee($secondNumber)
            ->assertSee($parcelNumber)
            ->assertSee('=Comercial Río SAC')
            ->assertSee('Rosa Quispe')
            ->assertSee('Pide factura')
            ->assertDontSee('receipt.issued');
        $this->actingAs($admin)->get(route('company.receipts.index', ['cliente' => '=Comercial']))
            ->assertSee($firstNumber)
            ->assertDontSee($secondNumber)
            ->assertDontSee('Rosa Quispe');
        $this->actingAs($admin)->get(route('company.receipts.index', ['tipo' => 'encomienda']))
            ->assertSee('Rosa Quispe')
            ->assertSee($parcelNumber)
            ->assertDontSee('=Comercial Río SAC');
        $this->actingAs($admin)->get(route('company.receipts.ticket', $ticket))->assertOk();
        $this->actingAs($admin)->get(route('company.receipts.guide', $shipment))->assertOk()->assertSee($shipment->code);

        $csv = $this->actingAs($admin)->get(route('company.receipts.export'))->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=Comercial Río SAC", $csv);
        $this->assertStringContainsString('95.00', $csv);
        $this->assertStringContainsString('25.00', $csv);
        $this->assertStringContainsString($firstNumber, $csv);
        $this->assertStringContainsString(DepartureSchedule::show($receipt->issued_at, 'Y-m-d H:i'), $csv);

        $this->actingAs($admin)->get(route('company.audit.index'))
            ->assertSee('emitió la constancia '.$firstNumber)
            ->assertDontSee('receipt.issued');

        $seller = $this->member($organization, 'company_counter');
        $operator = $this->member($organization, 'company_operator');
        $finance = $this->member($organization, 'company_finance');
        $this->actingAs($seller)->get(route('company.receipts.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.receipts.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.receipts.certificate', $receipt))->assertForbidden();
        $this->actingAs($finance)->get(route('company.receipts.index'))->assertOk()->assertSee('Comprobantes');

        $extra = Payment::query()->create([
            'reservation_id' => $invoiced->id,
            'purpose' => 'ticket',
            'method' => 'cash',
            'provider' => 'pos',
            'provider_reference' => 'EXTRA-'.$invoiced->code,
            'amount' => '1.00',
            'currency_code' => 'PEN',
            'status' => 'voided',
            'paid_at' => now(),
        ]);
        $this->expectException(UniqueConstraintViolationException::class);
        PaymentReceipt::query()->create([
            'organization_id' => $organization->id,
            'payment_id' => $extra->id,
            'series_year' => $year,
            'sequence' => 1,
            'number' => sprintf('CP-%d-999999', $year),
            'issued_at' => now(),
        ]);
    }

    public function test_a_company_cannot_open_another_companys_certificate(): void
    {
        [$admin, $organization] = $this->company('Dueña', '20674000002');
        app(CashDesk::class)->open($admin, $organization->id, null, 0);
        $departure = $this->departure($organization);
        $seat = $this->seat($departure, '1A', 1);
        $this->actingAs($admin)->post(route('company.pos.store'), $this->sale($departure, $seat, []))->assertRedirect();
        $receipt = PaymentReceipt::query()->where('organization_id', $organization->id)->firstOrFail();
        $year = (int) now()->timezone(DepartureSchedule::ZONE)->format('Y');
        $this->assertSame(sprintf('CP-%d-%06d', $year, 1), $receipt->number);

        [$other, $otherCompany] = $this->company('Ajena', '20674000003');
        app(CashDesk::class)->open($other, $otherCompany->id, null, 0);
        $otherDeparture = $this->departure($otherCompany);
        $otherSeat = $this->seat($otherDeparture, '1A', 1);
        $this->actingAs($other)->post(route('company.pos.store'), $this->sale($otherDeparture, $otherSeat, [
            'first_name' => 'Lia',
            'last_name' => 'Sol',
        ]))->assertRedirect();
        $otherReceipt = PaymentReceipt::query()->where('organization_id', $otherCompany->id)->firstOrFail();
        $this->assertSame(sprintf('CP-%d-%06d', $year, 1), $otherReceipt->number);
        $this->assertNotSame($receipt->id, $otherReceipt->id);

        $this->actingAs($other)->get(route('company.receipts.index'))
            ->assertOk()
            ->assertDontSee('Nila Vega')
            ->assertSee('Lia Sol');
        $this->actingAs($other)->get(route('company.receipts.certificate', $receipt))->assertNotFound();
        $this->actingAs($other)->get(route('company.receipts.ticket', Ticket::query()->firstOrFail()))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function sale(RouteDeparture $departure, VesselSeat $seat, array $extra): array
    {
        return array_merge([
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'first_name' => 'Nila',
            'last_name' => 'Vega',
            'document_type' => 'DNI',
            'document_number' => '40111222',
            'passenger_type' => 'adult',
            'hand_kg' => 0,
            'hold_kg' => 0,
            'method' => 'cash',
            'cash_tendered' => 95,
        ], $extra);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $name, string $ruc): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => $name, 'commercial_name' => $name,
            'ruc' => $ruc, 'email' => strtolower($ruc).'@test.pe', 'status' => 'active', 'modality' => 'fluvial',
        ]);
        $admin = User::factory()->create(['name' => 'Ada '.$name]);
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function member(Organization $organization, string $code): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => $code], ['name' => $code])->id, ['organization_id' => $organization->id]);
        $user->organizations()->attach($organization->id, ['status' => 'active']);

        return $user;
    }

    private function departure(Organization $organization): RouteDeparture
    {
        $origin = Port::query()->create(['name' => 'Origen '.$organization->id, 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Destino '.$organization->id, 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id, 'name' => 'Nave '.$organization->id, 'registration_number' => 'MAT-'.$organization->id,
            'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready',
        ]);
        $master = MasterRoute::query()->create([
            'code' => 'REC-'.$organization->id, 'modality' => 'fluvial', 'origin_city' => 'Iquitos', 'destination_city' => 'Nauta',
            'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active',
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

    private function seat(RouteDeparture $departure, string $code, int $column): VesselSeat
    {
        return $departure->vessel->seats()->create([
            'code' => $code, 'row_position' => 1, 'column_position' => $column, 'deck' => 'principal',
            'space_type' => 'seat', 'is_available' => true,
        ]);
    }
}
