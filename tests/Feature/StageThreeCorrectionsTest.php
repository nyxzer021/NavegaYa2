<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageThreeCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_sale_cannot_be_collected_and_a_legacy_commission_can_be_collected_or_voided(): void
    {
        [$admin, $organization, $departure] = $this->operator();
        $fresh = $this->payment($departure, 'NUEVA-100', 8, ['commission_model' => 'passenger_fee']);
        $collectible = $this->payment($departure, 'VIEJA-100', 8);
        $voidable = $this->payment($departure, 'VIEJA-200', 5);
        $period = [
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
        ];

        $this->actingAs($admin)
            ->from(route('admin.commissions.index'))
            ->post(route('admin.commissions.store', $organization), $period + [
                'payment_id' => $fresh->id,
                'reference' => 'Cobro indebido',
            ])
            ->assertRedirect(route('admin.commissions.index'))
            ->assertSessionHasErrors('payment_id');
        $this->assertSame('pending', $fresh->fresh()->commission_status);

        $this->actingAs($admin)
            ->post(route('admin.commissions.store', $organization), $period + [
                'payment_id' => $collectible->id,
                'reference' => 'Recibo 15',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('paid', $collectible->fresh()->commission_status);
        $this->assertSame('8.00', $collectible->fresh()->commission_amount);

        $this->actingAs($admin)
            ->post(route('admin.commissions.void', $organization), [
                'payment_id' => $voidable->id,
                'reason' => 'Venta de prueba',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('voided', $voidable->fresh()->commission_status);
        $this->assertSame('5.00', $voidable->fresh()->commission_amount);
        $this->assertTrue(AuditLog::query()->where('event', 'legacy_commission_voided')->where('subject_id', $voidable->id)->exists());

        $this->actingAs($admin)->get(route('admin.commissions.index'))
            ->assertOk()
            ->assertSee('Anulada')
            ->assertSeeInOrder(['Comisiones anteriores', 'S/ 8.00'])
            ->assertDontSee('S/ 13.00')
            ->assertDontSee('Registrar transferencia');
    }

    public function test_super_admin_correction_is_visible_to_the_company_and_the_company_cannot_change_its_ruc(): void
    {
        $admin = User::factory()->create(['name' => 'Super Revisión']);
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador'])->id);
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Nombre Viejo SAC',
            'commercial_name' => 'Nombre Viejo',
            'ruc' => '20111000033',
            'email' => 'identidad@navegaya.test',
            'status' => 'active',
            'modality' => 'fluvial',
            'base_city' => 'Iquitos',
            'address' => 'Malecón Viejo',
            'contact_name' => 'Ana Vieja',
        ]);
        $companyAdmin = User::factory()->create();
        $companyAdmin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $companyAdmin->organizations()->attach($organization->id, ['status' => 'active']);

        $this->actingAs($admin)->patch(route('admin.companies.identity.update', $organization), [
            'ruc' => '20111000044',
            'legal_name' => 'Nombre Nuevo SAC',
            'commercial_name' => 'Nombre Nuevo',
            'address' => 'Malecón Nuevo',
            'contact_name' => 'Ana Nueva',
            'reason' => 'El RUC de la ficha estaba mal',
        ])->assertRedirect();

        $organization->refresh();
        $this->assertSame('20111000044', $organization->ruc);
        $this->assertSame('Nombre Nuevo SAC', $organization->legal_name);
        $this->assertTrue(AuditLog::query()->where('event', 'company_identity_corrected')->where('subject_id', $organization->id)->exists());

        $this->actingAs($companyAdmin)->get(route('company.details.index'))
            ->assertOk()
            ->assertSee('Nombre Nuevo SAC')
            ->assertSee('20111000044')
            ->assertSee('Lo hizo Super Revisión')
            ->assertSee('El RUC de la ficha estaba mal');

        $this->actingAs($companyAdmin)->patch(route('company.details.update'), [
            'ruc' => '20999000000',
            'legal_name' => 'Nombre Nuevo SAC',
            'commercial_name' => 'Nombre Nuevo',
            'address' => 'Malecón Nuevo',
            'base_city' => 'Iquitos',
            'contact_name' => 'Ana Nueva',
        ])->assertRedirect();
        $this->assertSame('20111000044', $organization->fresh()->ruc);
    }

    /**
     * @return array{0: User, 1: Organization, 2: RouteDeparture}
     */
    private function operator(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Cobros SAC',
            'commercial_name' => 'Cobros',
            'ruc' => '20111000055',
            'email' => 'cobros@navegaya.test',
            'status' => 'active',
            'modality' => 'fluvial',
            'base_city' => 'Iquitos',
            'address' => 'Puerto',
            'contact_name' => 'Luis',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador'])->id);
        $origin = Port::create(['name' => 'Origen cobro', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Destino cobro', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $organization->id, 'name' => 'Lancha cobro', 'registration_number' => 'COB-01', 'vessel_type' => 'lancha', 'seat_capacity' => 10, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $organization->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'COB-01']);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 100, 'status' => 'scheduled']);

        return [$admin, $organization, $departure];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payment(RouteDeparture $departure, string $code, float $commission, array $payload = []): Payment
    {
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => $code,
            'contact_name' => 'Ana',
            'contact_email' => $code.'@viajero.test',
            'contact_phone' => '999000111',
            'base_amount' => 100,
            'discount_amount' => 0,
            'service_fee_amount' => $commission,
            'total_amount' => 100 + $commission,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        return Payment::create([
            'reservation_id' => $reservation->id,
            'purpose' => 'ticket',
            'method' => 'yape',
            'provider' => 'sandbox',
            'provider_reference' => 'PAY-'.$code,
            'provider_payload' => $payload,
            'amount' => 100 + $commission,
            'commission_rate' => 8,
            'commission_amount' => $commission,
            'operator_net' => 100,
            'commission_status' => 'pending',
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
    }
}
