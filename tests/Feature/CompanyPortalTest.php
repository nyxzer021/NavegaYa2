<?php

namespace Tests\Feature;

use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Payment;
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

class CompanyPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_departures_are_strictly_isolated_by_organization(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Uno', '20111111111');
        [, $other] = $this->companyAdmin('Operador Dos', '20222222222');
        $this->departure($organization, 'Nave Propia', 'Iquitos', 'Nauta');
        $this->departure($other, 'Nave Ajena', 'Yurimaguas', 'Lagunas');

        $this->actingAs($admin)->get(route('company.departures.index'))
            ->assertOk()->assertSee('Nave Propia')->assertSee('Iquitos')
            ->assertSee('md:hidden', false)
            ->assertDontSee('min-w-[980px]', false)
            ->assertDontSee('Nave Ajena')->assertDontSee('Yurimaguas');
    }

    public function test_company_admin_registers_fleet_and_bank_data_only_for_its_tenant(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Seguro', '20333333333');
        [, $other] = $this->companyAdmin('Operador Ajeno', '20444444444');
        $port = Port::create(['name' => 'Puerto Silico', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('company.fleet.vessels.store'), [
            'name' => 'Rápida Segura', 'registration_number' => 'DICAPI-001', 'vessel_type' => 'rapido',
            'seat_capacity' => 24, 'base_port_id' => $port->id, 'organization_id' => $other->id,
        ]);
        $vessel = Vessel::where('name', 'Rápida Segura')->firstOrFail();
        $response->assertRedirect(route('company.vessels.seats.edit', $vessel));
        $this->assertDatabaseHas('vessels', ['name' => 'Rápida Segura', 'organization_id' => $organization->id]);
        $this->assertDatabaseMissing('vessels', ['name' => 'Rápida Segura', 'organization_id' => $other->id]);
        $this->actingAs($admin)->patch(route('company.finance.bank'), [
            'bank_name' => 'Banco de la Nación', 'bank_account' => '001-123456', 'bank_cci' => '01800112345678901234',
        ])->assertRedirect();
        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'bank_name' => 'Banco de la Nación']);
        $this->assertDatabaseHas('organizations', ['id' => $other->id, 'bank_name' => null]);
    }

    public function test_company_admin_can_open_the_fleet_page(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Flota', '20333330000');
        Vessel::create([
            'organization_id' => $organization->id, 'name' => 'Rápida Segura',
            'registration_number' => 'DICAPI-FLOTA', 'vessel_type' => 'rapido',
            'seat_capacity' => 24, 'status' => 'draft',
        ]);

        $this->actingAs($admin)->get(route('company.fleet.index'))
            ->assertOk()->assertSee('Rápida Segura')->assertSee('Cerrar sesión')->assertSee($admin->name)
            ->assertSee('Asientos que puede vender')
            ->assertDontSee('text-[9px]', false);
    }

    public function test_company_seat_designer_starts_inside_capacity(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Plano', '20999990001');
        $vessel = Vessel::create([
            'organization_id' => $organization->id, 'name' => 'Henry II',
            'registration_number' => '1545245', 'vessel_type' => 'lancha',
            'seat_capacity' => 30, 'status' => 'draft',
        ]);

        $this->actingAs($admin)->get(route('company.vessels.seats.edit', $vessel))
            ->assertOk()
            ->assertSee('Asientos por fila, lado izquierdo')
            ->assertSee('Filas de esta cubierta')
            ->assertSee('Crear esta cubierta')
            ->assertSee('value="7"', false)
            ->assertSee('Te quedan')
            ->assertDontSee('>Filas<', false);
    }

    public function test_company_admin_can_publish_its_public_profile(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Ficha', '20777770001');

        $this->actingAs($admin)->get(route('company.profile.edit'))
            ->assertOk()
            ->assertSee('Página de la empresa')
            ->assertSee('Galería');

        $this->actingAs($admin)->patch(route('company.profile.update'), [
            'public_description' => 'Salidas diarias desde Pebas.',
            'service_hours' => 'Todos los días 06:00–18:00',
            'show_public_whatsapp' => '1',
        ])->assertRedirect();

        $this->assertSame('Salidas diarias desde Pebas.', $organization->fresh()->public_description);
        $this->assertSame('published', $organization->fresh()->profile_content_status);
    }

    public function test_company_can_enable_both_transports_from_its_fleet(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Mixto', '20888880001');

        $this->actingAs($admin)->get(route('company.fleet.index'))
            ->assertOk()
            ->assertSee('Lanchas y motonaves')
            ->assertSee('Habilitar avionetas')
            ->assertDontSee('Nueva aeronave');

        $this->actingAs($admin)->patch(route('company.fleet.transport.update'), [
            'transport' => 'aereo', 'enabled' => '1',
        ])->assertRedirect();

        $this->assertSame('mixto', $organization->fresh()->modality);
        $this->actingAs($admin)->get(route('company.fleet.index'))
            ->assertSee('Nueva embarcación')
            ->assertSee('Nueva aeronave');

        Vessel::create([
            'organization_id' => $organization->id, 'name' => 'Selva Fast',
            'registration_number' => 'DICAPI-MIX', 'vessel_type' => 'lancha',
            'seat_capacity' => 30, 'status' => 'draft',
        ]);
        $this->actingAs($admin)->patch(route('company.fleet.transport.update'), [
            'transport' => 'fluvial', 'enabled' => '0',
        ])->assertSessionHasErrors('transport');
        $this->assertSame('mixto', $organization->fresh()->modality);
    }

    public function test_company_manifest_is_available_only_for_its_own_departure(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Manifiesto', '20555555555');
        [, $other] = $this->companyAdmin('Operador Externo', '20666666666');
        $ownDeparture = $this->departure($organization, 'Nave DICAPI', 'Iquitos', 'Nauta');
        $foreignDeparture = $this->departure($other, 'Nave Externa', 'Nauta', 'Yurimaguas');

        $this->actingAs($admin)->get(route('company.departures.manifest', $ownDeparture))
            ->assertOk()
            ->assertSee('Manifiesto oficial de pasajeros')
            ->assertSee('0 pasajeros')
            ->assertDontSee('pasajero(s)')
            ->assertSee('Nave DICAPI');
        $this->actingAs($admin)->get(route('company.departures.manifest', $foreignDeparture))->assertForbidden();
        $ownDeparture->update(['departure_at' => now(), 'status' => 'scheduled']);
        $this->actingAs($admin)->get(route('company.counter.index'))
            ->assertOk()
            ->assertSee('Programada')
            ->assertDontSee('SCHEDULED');
    }

    public function test_company_admin_can_open_sales_dashboard_and_export_its_period(): void
    {
        [$admin] = $this->companyAdmin('Operador Comercial', '20777777777');

        $this->actingAs($admin)->get(route('company.sales.index'))
            ->assertOk()
            ->assertSee('Boletos vendidos de Operador Comercial')
            ->assertSee('Total pagado por viajeros')
            ->assertSee('Evolución de ingresos')
            ->assertSee('0 días con ventas')
            ->assertDontSee('día(s)');

        $this->actingAs($admin)->get(route('company.sales.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_company_admin_can_reprogram_a_departure_with_traceability(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Contingencia', '20888888888');
        $departure = $this->departure($organization, 'Nave Operativa', 'Iquitos', 'Nauta');
        $originalDepartureAt = $departure->departure_at;
        $newDepartureAt = now()->addDays(2)->setTime(10, 30);

        $response = $this->actingAs($admin)->patch(route('company.departures.contingency', $departure), [
            'action' => 'reschedule',
            'departure_at' => $newDepartureAt->format('Y-m-d H:i:s'),
            'reason' => 'Cierre preventivo del puerto por seguridad.',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $departure->refresh();
        $this->assertSame('rescheduled', $departure->status);
        $this->assertSame($originalDepartureAt->format('Y-m-d H:i:s'), $departure->previous_departure_at->format('Y-m-d H:i:s'));
        $this->assertSame($newDepartureAt->format('Y-m-d H:i:s'), $departure->departure_at->format('Y-m-d H:i:s'));
        $this->assertSame($admin->id, $departure->status_changed_by_user_id);
        $this->assertSame('Cierre preventivo del puerto por seguridad.', $departure->disruption_reason);
    }

    public function test_company_admin_cannot_change_another_company_departure(): void
    {
        [$admin] = $this->companyAdmin('Operador Propio', '20999999991');
        [, $otherOrganization] = $this->companyAdmin('Operador Ajeno', '20999999992');
        $foreignDeparture = $this->departure($otherOrganization, 'Nave Ajena', 'Nauta', 'Lagunas');

        $response = $this->actingAs($admin)->patch(route('company.departures.contingency', $foreignDeparture), [
            'action' => 'cancel',
            'reason' => 'Intento no autorizado.',
        ]);

        $response->assertForbidden();
        $this->assertSame('scheduled', $foreignDeparture->fresh()->status);
    }

    public function test_company_cannot_sell_on_a_cancelled_departure(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Venta Segura', '20999999993');
        $departure = $this->departure($organization, 'Nave Cerrada', 'Iquitos', 'Pebas');
        $departure->update(['departure_at' => now()->setTime(15, 0), 'status' => 'cancelled', 'is_published' => false]);
        $seat = $departure->vessel->seats()->create(['code' => '1A', 'row_position' => 1, 'column_position' => 1, 'deck' => 1, 'is_available' => true]);

        $response = $this->actingAs($admin)->post(route('company.pos.store'), [
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'passenger_name' => 'Pasajero Seguro',
            'passenger_age' => 30,
            'phone' => '999999999',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('reservations', ['route_departure_id' => $departure->id]);
    }

    public function test_counter_sale_charges_the_fare_without_a_navega_ya_commission(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Muelle', '20999999995');
        $organization->update(['commission_rate' => 7.5, 'physical_sales_commission_rate' => 5]);
        $departure = $this->departure($organization, 'Nave Muelle', 'Iquitos', 'Pebas');
        $departure->update(['departure_at' => now()->setTime(15, 0), 'status' => 'scheduled', 'is_published' => true]);
        $seat = $departure->vessel->seats()->create(['code' => '1A', 'row_position' => 1, 'column_position' => 1, 'deck' => 1, 'is_available' => true]);

        $this->actingAs($admin)->post(route('company.pos.store'), [
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'document_type' => 'DNI',
            'document_number' => '12345678',
            'passenger_name' => 'Pasajero de muelle',
            'passenger_age' => 30,
            'phone' => '999999999',
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'method' => 'cash_dock',
            'commission_rate' => 0,
            'commission_amount' => 0,
        ]);
    }

    public function test_invalid_departure_times_are_explained_in_spanish(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Horario', '20999999996');
        $departure = $this->departure($organization, 'Nave Horario', 'Iquitos', 'Nauta');

        $this->actingAs($admin)->from(route('company.departures.create'))->post(route('company.departures.store'), [
            'master_route_id' => $departure->transportRoute->master_route_id,
            'vessel_id' => $departure->vessel_id,
            'departure_at' => '2026-11-05 17:12:00',
            'boarding_starts_at' => '2026-11-06 17:13:00',
            'estimated_arrival_at' => '2026-11-04 17:13:00',
            'fare' => '200',
        ])->assertRedirect(route('company.departures.create'))
            ->assertSessionHasErrors([
                'boarding_starts_at' => 'El inicio de embarque debe ser antes o a la misma hora de la salida.',
                'estimated_arrival_at' => 'La llegada estimada debe ser después de la salida.',
            ]);
    }

    public function test_schedule_form_accepts_separate_date_and_time(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Fecha', '20999999997');
        $departure = $this->departure($organization, 'Nave Fecha', 'Iquitos', 'Lagunas');

        $this->actingAs($admin)->post(route('company.departures.store'), [
            'master_route_id' => $departure->transportRoute->master_route_id,
            'vessel_id' => $departure->vessel_id,
            'departure_date' => '2026-11-05',
            'departure_time' => '17:12',
            'fare' => '180',
        ])->assertRedirect(route('company.departures.index'));

        $created = RouteDeparture::query()->where('fare', 180)->first();
        $this->assertNotNull($created);
        $this->assertSame('2026-11-05 17:12:00', $created->departure_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05 16:27:00', $created->boarding_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05 20:12:00', $created->estimated_arrival_at->format('Y-m-d H:i:s'));
    }

    public function test_future_departure_shows_manifest_without_boarding_button(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Itinerario', '20999999998');
        $this->departure($organization, 'Nave Futura', 'Lagunas', 'Pebas');

        $this->actingAs($admin)->get(route('company.departures.index'))
            ->assertOk()
            ->assertSee('Itinerario')
            ->assertSee('Manifiesto')
            ->assertDontSee('>Embarque</a>', false);
    }

    public function test_pending_company_can_prepare_its_profile_and_fleet_but_cannot_operate(): void
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Operador en Validación',
            'ruc' => '20999999994',
            'email' => 'pendiente@test.pe',
            'status' => 'pending',
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'pending']);

        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()
            ->assertSee('Validación pendiente')
            ->assertSee('Mi empresa')
            ->assertSee('data-menu-lead="configuracion"', false)
            ->assertDontSee('Vender pasaje');
        $this->actingAs($admin)->get(route('company.fleet.index'))->assertOk();
        $this->actingAs($admin)->get(route('company.departures.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('company.counter.index'))->assertNotFound();
        $this->actingAs($admin)->get(route('company.pos.index'))->assertNotFound();
    }

    public function test_active_company_dashboard_leads_with_todays_departures_and_confirmed_occupancy(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador del Día', '20999999995');
        $organization->update(['commission_rate' => 7.5]);
        $departure = $this->departure($organization, 'Nave del Día', 'Iquitos', 'Pebas');
        $departure->update(['departure_at' => now()->setTime(15, 0), 'status' => 'scheduled']);
        $departure->vessel->update([
            'seat_capacity' => 4,
            'maintenance_scheduled_on' => now()->addDays(10)->toDateString(),
            'maintenance_kind' => 'reparacion',
        ]);
        $confirmedSeat = $departure->vessel->seats()->create(['code' => '1A', 'row_position' => 1, 'column_position' => 1, 'deck' => 1, 'is_available' => true]);
        $heldSeat = $departure->vessel->seats()->create(['code' => '1B', 'row_position' => 1, 'column_position' => 2, 'deck' => 1, 'is_available' => true]);
        $confirmed = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'RES-DIA',
            'contact_name' => 'Ana',
            'contact_email' => 'ana@viajero.test',
            'contact_phone' => '999000111',
            'total_amount' => 110,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        ReservationSeat::create([
            'reservation_id' => $confirmed->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $confirmedSeat->id,
            'passenger_name' => 'Ana Confirmada',
            'document_number' => '12345678',
        ]);
        $pending = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'RES-HOLD',
            'contact_name' => 'Luis',
            'contact_email' => 'luis@viajero.test',
            'contact_phone' => '999000222',
            'total_amount' => 110,
            'status' => 'pending_payment',
        ]);
        ReservationSeat::create([
            'reservation_id' => $pending->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $heldSeat->id,
            'passenger_name' => 'Luis en espera',
            'document_number' => '87654321',
        ]);
        Payment::create([
            'reservation_id' => $confirmed->id,
            'purpose' => 'ticket',
            'method' => 'yape',
            'provider' => 'sandbox',
            'provider_reference' => 'PAY-DIA',
            'amount' => 110,
            'commission_rate' => 7.5,
            'commission_amount' => 8.25,
            'operator_net' => 101.75,
            'commission_status' => 'pending',
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()
            ->assertSee('data-menu-lead="operacion"', false)
            ->assertSee('Mi empresa')
            ->assertSeeInOrder(['Mi empresa', 'Canales de venta', 'Ventas y finanzas', 'Operación'])
            ->assertSee('Inicio')
            ->assertSee('Pasajes vendidos')
            ->assertSee('1 registrada · 1 habilitada')
            ->assertSee('En línea')
            ->assertSee('Asientos de la flota')
            ->assertDontSee('Salidas diarias por embarcación')
            ->assertSee('Mantenimiento programado')
            ->assertSee('Reparación')
            ->assertDontSee('Gastos de mantenimiento')
            ->assertSee('Salidas de hoy')
            ->assertDontSee('15:00')
            ->assertDontSee('3 libres')
            ->assertSee('25%')
            ->assertSee('1 de 4 asientos')
            ->assertSee('S/ 110.00')
            ->assertDontSee('S/ 8.25')
            ->assertDontSee('Comisión del convenio')
            ->assertSee('Punto de venta')
            ->assertDontSee('Embarcar')
            ->assertDontSee('Vender pasaje')
            ->assertDontSee('Cobrado hoy')
            ->assertDontSee('Cuenta bancaria');
    }

    public function test_boarding_uses_one_list_when_only_today_has_a_departure(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Embarque', '20999999993');
        $departure = $this->departure($organization, 'Nave de Hoy', 'Iquitos', 'Lagunas');
        $departure->update(['departure_at' => now()->setTime(15, 30)]);

        $this->actingAs($admin)->get(route('company.counter.index'))
            ->assertOk()
            ->assertSee('15:30')
            ->assertDontSee('No hay rutas programadas');
    }

    private function companyAdmin(string $name, string $ruc): array
    {
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => $name, 'ruc' => $ruc, 'email' => strtolower(str_replace(' ', '', $name)).'@test.pe', 'status' => 'active']);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function departure(Organization $organization, string $vesselName, string $originCity, string $destinationCity): RouteDeparture
    {
        $origin = Port::create(['name' => 'Puerto '.$originCity.$organization->id, 'city' => $originCity, 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto '.$destinationCity.$organization->id, 'city' => $destinationCity, 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::create(['organization_id' => $organization->id, 'name' => $vesselName, 'registration_number' => 'MAT-'.$organization->id, 'vessel_type' => 'rapido', 'seat_capacity' => 20, 'status' => 'ready']);
        $masterRoute = MasterRoute::create([
            'code' => 'TRM-TEST-'.$organization->id,
            'modality' => 'fluvial',
            'origin_city' => $originCity,
            'destination_city' => $destinationCity,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'status' => 'active',
        ]);
        $route = TransportRoute::create(['organization_id' => $organization->id, 'master_route_id' => $masterRoute->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id]);

        return RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 95, 'status' => 'scheduled']);
    }
}
