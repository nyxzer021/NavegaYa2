<?php

namespace Tests\Feature;

use App\Mail\ConsumerClaimAnsweredMail;
use App\Mail\ConsumerClaimFiledMail;
use App\Models\ConsumerClaim;
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
use App\Support\LimaBusinessCalendar;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConsumerClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_claim_from_a_ticket_keeps_its_number_its_pdf_and_a_locked_answer(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-07-24 15:00:00', 'America/Lima'));
        [$admin, $organization] = $this->company('Río Libro SAC', '20691000001');
        [$other] = $this->company('Otra Libro SAC', '20691000002');
        $seller = $this->member($organization, 'company_counter');
        $operator = $this->member($organization, 'company_operator');
        $finance = $this->member($organization, 'company_finance');
        $super = $this->superAdmin();
        $reservation = $this->reservation($organization);

        $this->get(route('tickets.show', $reservation->code))
            ->assertOk()
            ->assertSee(__('claims.book'))
            ->assertSee('empresa='.$organization->id, false)
            ->assertSee('reserva='.$reservation->code, false);
        $this->get(route('claims.create', ['empresa' => $organization->id, 'reserva' => $reservation->code]))
            ->assertOk()
            ->assertSee('Río Libro SAC')
            ->assertSee('20691000001')
            ->assertSee($reservation->code)
            ->assertSee(__('claims.type_reclamo_help'))
            ->assertSee(__('claims.type_queja_help'));
        $this->get(route('home'))->assertOk()->assertSee(__('claims.book'));

        $this->post(route('claims.store'), $this->payload($organization->id, [
            'reservation_code' => $reservation->code,
            'consumer_name' => '=Ana Reclamo',
            'amount' => '80.00',
            'good_description' => 'Pasaje fluvial Iquitos → Nauta',
        ]))->assertRedirect();

        $claim = ConsumerClaim::query()->firstOrFail();
        $this->assertSame('RN-2026-000001', $claim->code);
        $this->assertSame('2026-08-18', $claim->due_on->toDateString());
        $this->assertSame($reservation->id, $claim->reservation_id);
        $this->get(route('claims.show', $claim->public_token))
            ->assertOk()
            ->assertSee('RN-2026-000001')
            ->assertSee('24/07/2026')
            ->assertSee('Río Libro SAC')
            ->assertSee('20691000001')
            ->assertSee('=Ana Reclamo')
            ->assertSee('data-tone="leaf"', false);
        $pdf = $this->get(route('claims.pdf', $claim->public_token));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringContainsString('RN-2026-000001', $pdf->getContent());
        $this->assertStringContainsString('24/07/2026', $pdf->getContent());
        $this->assertStringContainsString('=Ana Reclamo', $pdf->getContent());
        Mail::assertSent(ConsumerClaimFiledMail::class);

        $this->actingAs($admin)->get(route('company.claims.index'))
            ->assertOk()
            ->assertSee('Libro de Reclamaciones')
            ->assertSee('RN-2026-000001')
            ->assertSee('data-tone="leaf"', false);
        $this->actingAs($finance)->get(route('company.claims.index'))->assertOk()->assertSee('RN-2026-000001');
        $this->actingAs($other)->get(route('company.claims.index'))->assertOk()->assertDontSee('RN-2026-000001');
        $this->actingAs($seller)->get(route('company.claims.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.claims.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('company.claims.export'))->assertForbidden();
        $this->actingAs($other)->post(route('company.claims.respond', $claim), ['response' => 'No corresponde a esta empresa responder así.'])->assertNotFound();

        $this->travelTo(Carbon::parse('2026-08-12 10:00:00', 'America/Lima'));
        $this->actingAs($admin)->get(route('company.claims.index'))
            ->assertSee('data-tone="sun"', false)
            ->assertSee('Quedan 4 días hábiles');
        $this->travelTo(Carbon::parse('2026-08-19 10:00:00', 'America/Lima'));
        $this->actingAs($admin)->get(route('company.claims.index'))
            ->assertSee('data-tone="coral"', false)
            ->assertSee(__('claims.overdue'));

        $this->actingAs($admin)->post(route('company.claims.respond', $claim), [
            'response' => 'La salida salió con demora y devolvemos el pasaje.',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('company.claims.respond', $claim), [
            'response' => 'Un segundo texto que no debe guardarse.',
        ])->assertSessionHasErrors('response');
        $this->assertSame('La salida salió con demora y devolvemos el pasaje.', $claim->fresh()->response_text);
        $this->get(route('claims.show', $claim->public_token))->assertSee('La salida salió con demora y devolvemos el pasaje.');
        Mail::assertSent(ConsumerClaimAnsweredMail::class);
        $this->delete(route('claims.show', $claim->public_token))->assertStatus(405);

        $csv = $this->actingAs($admin)->get(route('company.claims.export'))->streamedContent();
        $this->assertStringContainsString('RN-2026-000001', $csv);
        $this->assertStringContainsString("'=Ana Reclamo", $csv);

        $this->actingAs($super)->get(route('admin.claims.index'))->assertOk()->assertSee('RN-2026-000001');
        $this->actingAs($super)->post(route('admin.claims.respond', $claim), [
            'response' => 'El superadmin no responde el reclamo de la empresa.',
        ])->assertForbidden();

        $this->post(route('claims.store'), $this->payload(null, [
            'provider' => 'platform',
            'organization_id' => null,
            'consumer_name' => 'Luis Plataforma',
            'detail' => 'El cargo de servicio se cobró dos veces en el sitio.',
        ]))->assertRedirect();
        $this->assertSame('RN-2026-000001', ConsumerClaim::query()->where('provider', 'platform')->value('code'));
        $platform = ConsumerClaim::query()->where('provider', 'platform')->firstOrFail();
        $this->actingAs($super)->post(route('admin.claims.respond', $platform), [
            'response' => 'El cargo de servicio se revisó y se devuelve.',
        ])->assertRedirect();
        $this->actingAs($super)->post(route('admin.claims.respond', $platform), [
            'response' => 'Un segundo texto que no debe guardarse.',
        ])->assertSessionHasErrors('response');
        $this->assertSame('El cargo de servicio se revisó y se devuelve.', $platform->fresh()->response_text);
        $this->post(route('claims.store'), $this->payload($organization->id, [
            'consumer_name' => 'Mario Segundo',
            'detail' => 'La embarcación no tenía los chalecos anunciados.',
        ]))->assertRedirect();
        $this->assertSame('RN-2026-000002', ConsumerClaim::query()->where('consumer_name', 'Mario Segundo')->value('code'));
    }

    public function test_the_deadline_skips_weekends_and_national_holidays(): void
    {
        $calendar = new LimaBusinessCalendar;
        $friday = Carbon::parse('2026-07-24', 'America/Lima');
        $this->assertFalse($calendar->isBusinessDay(Carbon::parse('2026-07-25', 'America/Lima')));
        $this->assertFalse($calendar->isBusinessDay(Carbon::parse('2026-07-28', 'America/Lima')));
        $this->assertSame('2026-08-18', $calendar->dueOn($friday)->toDateString());
        $this->travelTo(Carbon::parse('2026-08-12 10:00:00', 'America/Lima'));
        $this->assertSame(['days' => 4, 'overdue' => false, 'tone' => 'sun'], $calendar->countdown('2026-08-18'));
        $this->travelTo(Carbon::parse('2026-08-19 10:00:00', 'America/Lima'));
        $this->assertSame(['days' => 0, 'overdue' => true, 'tone' => 'coral'], $calendar->countdown('2026-08-18'));
    }

    public function test_a_repeated_number_for_the_same_provider_is_rejected(): void
    {
        Mail::fake();
        [$ignored, $organization] = $this->company('Única SAC', '20691000003');
        $this->post(route('claims.store'), $this->payload($organization->id))->assertRedirect();
        $claim = ConsumerClaim::query()->firstOrFail();
        $copy = $claim->replicate();
        $copy->public_token = str_repeat('a', 40);

        $this->expectException(UniqueConstraintViolationException::class);
        $copy->save();
    }

    public function test_a_minor_needs_a_guardian_and_the_form_is_rate_limited(): void
    {
        Mail::fake();
        [, $organization] = $this->company('Menor SAC', '20691000004');
        $this->post(route('claims.store'), $this->payload($organization->id, [
            'is_minor' => '1',
        ]))->assertSessionHasErrors(['guardian_name', 'guardian_document', 'guardian_relationship']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('claims.store'), $this->payload($organization->id, [
                'consumer_name' => 'Consumidor '.$i,
                'email' => 'c'.$i.'@viajero.test',
            ]))->assertRedirect();
        }
        $this->post(route('claims.store'), $this->payload($organization->id, [
            'consumer_name' => 'Consumidor extra',
            'email' => 'extra@viajero.test',
        ]))->assertStatus(429);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(?int $organizationId, array $extra = []): array
    {
        return array_merge([
            'provider' => 'company',
            'organization_id' => $organizationId,
            'reservation_code' => '',
            'consumer_name' => 'Ana Reclamo',
            'document_type' => 'dni',
            'document_number' => '44556677',
            'address' => 'Calle Napo 120, Iquitos',
            'phone' => '065600100',
            'email' => 'ana.reclamo@viajero.test',
            'good_kind' => 'service',
            'amount' => '50.00',
            'good_description' => 'Pasaje fluvial de ida',
            'claim_type' => 'reclamo',
            'detail' => 'El servicio no se prestó como estaba publicado.',
            'request_text' => 'Pido la devolución del pasaje.',
            'email_accepted' => '1',
        ], $extra);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(string $name, string $ruc): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'commercial_name' => $name,
            'ruc' => $ruc,
            'email' => $ruc.'@test.pe',
            'status' => 'active',
            'modality' => 'fluvial',
        ]);
        $admin = User::factory()->create();
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

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super'])->id);

        return $user;
    }

    private function reservation(Organization $organization): Reservation
    {
        $origin = Port::query()->create(['name' => 'Origen Libro', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::query()->create(['name' => 'Destino Libro', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Nave Libro',
            'registration_number' => 'LIB-1',
            'vessel_type' => 'lancha',
            'seat_capacity' => 10,
            'status' => 'ready',
        ]);
        $seat = VesselSeat::query()->create([
            'vessel_id' => $vessel->id,
            'code' => '1A',
            'deck' => 'principal',
            'row_position' => 1,
            'column_position' => 1,
            'space_type' => 'seat',
        ]);
        $route = TransportRoute::query()->create([
            'organization_id' => $organization->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'code' => 'LIB-1',
        ]);
        $departure = RouteDeparture::query()->create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDay(),
            'fare' => 80,
            'status' => 'scheduled',
            'is_published' => true,
        ]);
        $reservation = Reservation::query()->create([
            'route_departure_id' => $departure->id,
            'code' => 'BOL-LIBRO',
            'contact_name' => 'Ana Reclamo',
            'contact_email' => 'ana.reclamo@viajero.test',
            'contact_phone' => '065600100',
            'total_amount' => 80,
            'status' => 'confirmed',
            'sales_channel' => 'web',
            'paid_at' => now(),
        ]);
        $seatRow = ReservationSeat::query()->create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Ana Reclamo',
            'document_number' => '44556677',
        ]);
        Ticket::query()->create([
            'reservation_seat_id' => $seatRow->id,
            'code' => 'TKT-LIBRO',
            'boarding_token' => 'token-libro',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        return $reservation;
    }
}
