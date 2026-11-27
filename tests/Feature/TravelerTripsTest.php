<?php

namespace Tests\Feature;

use App\Models\ConsumerClaim;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\TicketRefund;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TravelerTripsTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_trip_shows_qr_and_actions(): void
    {
        $traveler = $this->traveler('proximo@viajero.test');
        $departureAt = now()->addDays(10)->startOfHour();
        $lima = $departureAt->copy()->timezone('America/Lima');
        $trip = $this->riverTrip($traveler, 'NY-NEXT-11', 'BOL-NEXT-11', '12B', 'Puerto Bellavista 11', 'Lancha Manati', $departureAt);

        $this->actingAs($traveler)->get(route('travels.index'))
            ->assertOk()
            ->assertSeeInOrder([
                __('travels.next'),
                'Iquitos',
                'Nauta',
                $lima->format('d/m/Y'),
                $lima->format('H:i'),
                'Puerto Bellavista 11',
                'Lancha Manati',
                '12B',
                __('travels.view_ticket'),
                __('travels.download_pdf'),
                route('tickets.qr', $trip['ticket']->code),
            ])
            ->assertSee(route('tickets.show', $trip['reservation']->code), false)
            ->assertSee(route('tickets.pdf', $trip['ticket']->code), false);
    }

    public function test_a_refunded_trip_whose_seat_was_released_still_opens_every_ticket_page(): void
    {
        $traveler = $this->traveler('devuelto@viajero.test');
        $trip = $this->riverTrip($traveler, 'NY-DEV-11', 'BOL-DEV-11', '7C', 'Puerto Devuelto', 'Lancha Devuelta', now()->addDays(5));
        $trip['reservation']->seats()->update(['vessel_seat_id' => null]);
        $trip['ticket']->update(['status' => 'voided']);

        $this->actingAs($traveler)->get(route('travels.index'))->assertOk()->assertSee('NY-DEV-11');
        $this->actingAs($traveler)->get(route('tickets.show', 'NY-DEV-11'))->assertOk();
        $this->get(route('tickets.pdf', 'BOL-DEV-11'))->assertOk();
    }

    public function test_upcoming_and_history_are_separate_tabs_and_history_is_paginated(): void
    {
        $traveler = $this->traveler('pestanas@viajero.test');
        $this->riverTrip($traveler, 'NY-FUT-01', 'BOL-FUT-01', '1A', 'Puerto Futuro', 'Lancha Futura', now()->addDays(4));
        foreach (range(1, 12) as $n) {
            $code = sprintf('NY-PAS-%02d', $n);
            $this->riverTrip($traveler, $code, sprintf('BOL-H%02d', $n), 'P'.$n, 'Puerto Pasado '.$n, 'Lancha Pasada '.$n, now()->subDays($n));
        }

        $this->actingAs($traveler)->get(route('travels.index'))
            ->assertOk()
            ->assertSee(__('travels.tab_upcoming'))
            ->assertSee(__('travels.tab_history'))
            ->assertSee('data-trip="NY-FUT-01"', false)
            ->assertDontSee('data-trip="NY-PAS-01"', false);

        $this->actingAs($traveler)->get(route('travels.index', ['vista' => 'historial']))
            ->assertOk()
            ->assertSee('data-trip="NY-PAS-01"', false)
            ->assertSee('data-trip="NY-PAS-10"', false)
            ->assertDontSee('data-trip="NY-PAS-11"', false)
            ->assertDontSee('data-trip="NY-FUT-01"', false);

        $this->actingAs($traveler)->get(route('travels.index', ['vista' => 'historial', 'pagina' => 2]))
            ->assertOk()
            ->assertSee('data-trip="NY-PAS-11"', false)
            ->assertSee('data-trip="NY-PAS-12"', false)
            ->assertDontSee('data-trip="NY-PAS-01"', false);
    }

    public function test_pending_payment_shows_complete_payment(): void
    {
        $traveler = $this->traveler('pendiente@viajero.test');
        $trip = $this->riverTrip($traveler, 'NY-PAY-11', null, '4C', 'Puerto Pendiente 11', 'Lancha Espera', now()->addDays(3), 'pending_payment');

        $this->actingAs($traveler)->get(route('travels.index'))
            ->assertOk()
            ->assertSee(__('travels.complete_payment'))
            ->assertSee(route('payments.show', $trip['reservation']->code), false)
            ->assertDontSee(__('travels.next'))
            ->assertDontSee(__('travels.view_ticket'))
            ->assertDontSee(__('travels.ask_refund'));
    }

    public function test_a_paid_refund_moves_the_trip_to_history_as_refunded_without_pdf(): void
    {
        $traveler = $this->traveler('devuelto-pagado@viajero.test');
        $trip = $this->riverTrip($traveler, 'NY-DVP-11', 'BOL-DVP-11', '9A', 'Puerto Devuelto Pagado', 'Lancha Devuelta', now()->addDays(6));
        $payment = Payment::create([
            'reservation_id' => $trip['reservation']->id, 'purpose' => 'ticket', 'method' => 'yape', 'provider' => 'sandbox',
            'provider_reference' => 'PAY-DVP-11', 'amount' => 95, 'status' => 'confirmed', 'paid_at' => now(),
        ]);
        TicketRefund::create([
            'organization_id' => $trip['company']->id, 'reservation_id' => $trip['reservation']->id, 'payment_id' => $payment->id,
            'source' => 'passenger', 'cause' => 'regret', 'status' => 'paid', 'fare_amount' => 95, 'fee_amount' => 0, 'amount' => 95,
            'explanation' => 'Devolución pagada.',
        ]);
        $trip['ticket']->update(['status' => 'voided']);

        $this->actingAs($traveler)->get(route('travels.index'))
            ->assertOk()
            ->assertDontSee(__('travels.next'));

        $this->actingAs($traveler)->get(route('travels.index', ['vista' => 'proximos']))
            ->assertOk()
            ->assertDontSee('data-trip="NY-DVP-11"', false);

        $this->actingAs($traveler)->get(route('travels.index', ['vista' => 'historial']))
            ->assertOk()
            ->assertSee('data-trip="NY-DVP-11"', false)
            ->assertSee(__('travels.status_refunded'))
            ->assertDontSee(route('tickets.pdf', 'BOL-DVP-11'), false);
    }

    public function test_the_traveler_downloads_the_payment_certificate_only_for_an_own_paid_trip(): void
    {
        $traveler = $this->traveler('constancia@viajero.test');
        $trip = $this->riverTrip($traveler, 'NY-CON-01', 'BOL-CON-01', '3A', 'Puerto Constancia', 'Lancha Constancia', now()->addDays(4));

        $this->actingAs($traveler)->get(route('tickets.certificate', 'NY-CON-01'))->assertNotFound();

        Payment::create([
            'reservation_id' => $trip['reservation']->id, 'purpose' => 'ticket', 'method' => 'yape', 'provider' => 'sandbox',
            'provider_reference' => 'PAY-CON-01', 'amount' => 95, 'status' => 'confirmed', 'paid_at' => now(),
        ]);

        $this->actingAs($traveler)->get(route('travels.index'))
            ->assertOk()
            ->assertSee(route('tickets.certificate', 'NY-CON-01'), false);
        $response = $this->actingAs($traveler)->get(route('tickets.certificate', 'NY-CON-01'));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $stranger = $this->traveler('ajeno-constancia@viajero.test');
        $this->actingAs($stranger)->get(route('tickets.certificate', 'NY-CON-01'))->assertForbidden();
    }

    public function test_a_completed_trip_can_be_rated_once_from_my_trips(): void
    {
        $traveler = $this->traveler('valora@viajero.test');
        $trip = $this->riverTrip($traveler, 'NY-VAL-01', 'BOL-VAL-01', '4A', 'Puerto Valora', 'Lancha Valora', now()->subDays(2));
        $trip['reservation']->departure->update(['status' => 'completed']);

        $this->actingAs($traveler)->get(route('travels.index', ['vista' => 'historial']))
            ->assertOk()
            ->assertSee(__('travels.rate_trip'))
            ->assertSee(route('companies.review', $trip['company']->id), false);

        $this->actingAs($traveler)->post(route('companies.review', $trip['company']->id), [
            'reservation_id' => $trip['reservation']->id, 'rating' => 4, 'comment' => 'Salió puntual y la atención fue buena.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($traveler)->get(route('travels.index', ['vista' => 'historial']))
            ->assertOk()
            ->assertSee(__('travels.reviewed', ['stars' => 4]))
            ->assertDontSee(__('travels.send_review'));
    }

    public function test_open_refund_shows_status_and_hides_request(): void
    {
        $traveler = $this->traveler('reembolso@viajero.test');
        $trip = $this->riverTrip($traveler, 'NY-REF-11', 'BOL-REF-11', '8A', 'Puerto Reembolso 11', 'Lancha Devolucion', now()->addDays(4));
        $payment = Payment::create([
            'reservation_id' => $trip['reservation']->id,
            'purpose' => 'ticket',
            'method' => 'yape',
            'provider' => 'sandbox',
            'provider_reference' => 'PAY-REF-11',
            'amount' => 95,
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
        TicketRefund::create([
            'organization_id' => $trip['company']->id,
            'reservation_id' => $trip['reservation']->id,
            'payment_id' => $payment->id,
            'source' => 'passenger',
            'cause' => 'regret',
            'status' => 'requested',
            'fare_amount' => 95,
            'fee_amount' => 0,
            'amount' => 95,
            'explanation' => 'El viajero pidió la devolución antes de la salida.',
        ]);

        $this->actingAs($traveler)->get(route('travels.index'))
            ->assertOk()
            ->assertSee(__('refunds.status_requested'))
            ->assertDontSee(__('travels.ask_refund'))
            ->assertSee(__('travels.view_ticket'));
    }

    public function test_traveler_does_not_see_another_persons_trips_or_claims(): void
    {
        $owner = $this->traveler('dueno@viajero.test');
        $other = $this->traveler('ajeno@viajero.test');
        $trip = $this->riverTrip($owner, 'NY-OWN-11', 'BOL-OWN-11', '2D', 'Puerto Propio 11', 'Lancha Propia', now()->addDays(2));
        $claim = $this->claim($owner->email, 'HR-OWN-11', 'token-own-11');

        $this->actingAs($owner)->get(route('travels.index'))
            ->assertOk()
            ->assertSee($claim->code)
            ->assertSee('20/11/2026')
            ->assertSee(__('claims.traveler_within'))
            ->assertSee(route('claims.show', $claim->public_token), false);

        $this->actingAs($other)->get(route('travels.index'))
            ->assertOk()
            ->assertDontSee($trip['reservation']->code)
            ->assertDontSee($trip['ticket']->code)
            ->assertDontSee($claim->code)
            ->assertDontSee($claim->public_token)
            ->assertDontSee(__('travels.claims'));
    }

    public function test_unverified_email_hides_claims(): void
    {
        $traveler = $this->traveler('sinverificar@viajero.test', false);
        $claim = $this->claim($traveler->email, 'HR-HIDE-11', 'token-hide-11');
        $this->riverTrip($traveler, 'NY-HIDE-11', 'BOL-HIDE-11', '5E', 'Puerto Oculto 11', 'Lancha Oculta', now()->addDays(2));

        $this->actingAs($traveler)->get(route('travels.index'))
            ->assertOk()
            ->assertSee($traveler->name)
            ->assertDontSee($claim->code)
            ->assertDontSee($claim->public_token)
            ->assertDontSee(__('travels.claims'))
            ->assertDontSee(__('claims.traveler_within'))
            ->assertDontSee(__('claims.traveler_answered'));
    }

    private function traveler(string $email, bool $verified = true): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'email_verified_at' => $verified ? now() : null,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente / pasajero']));

        return $user;
    }

    /**
     * @return array{reservation: Reservation, ticket: ?Ticket, company: Organization}
     */
    private function riverTrip(User $traveler, string $reservationCode, ?string $ticketCode, string $seatCode, string $portName, string $vesselName, Carbon $departureAt, string $status = 'confirmed'): array
    {
        $suffix = substr(md5($reservationCode), 0, 6);
        $company = Organization::create([
            'type' => 'transport_company',
            'legal_name' => "Operador {$suffix}",
            'ruc' => '20'.substr(str_pad((string) abs(crc32($reservationCode)), 9, '0'), 0, 9),
            'email' => "{$suffix}@operator.test",
            'status' => 'active',
        ]);
        $origin = Port::create(['name' => $portName, 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => "Destino {$suffix}", 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create([
            'organization_id' => $company->id,
            'name' => $vesselName,
            'registration_number' => "PA-{$suffix}",
            'vessel_type' => 'rapida',
            'seat_capacity' => 10,
            'status' => 'ready',
        ]);
        $seat = VesselSeat::create([
            'vessel_id' => $vessel->id,
            'code' => $seatCode,
            'deck' => 'principal',
            'seat_class' => 'standard',
            'row_position' => 1,
            'column_position' => 1,
        ]);
        $route = TransportRoute::create([
            'organization_id' => $company->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'code' => "R-{$suffix}",
        ]);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => $departureAt,
            'fare' => 95,
            'status' => 'scheduled',
        ]);
        $reservation = Reservation::create([
            'user_id' => $traveler->id,
            'route_departure_id' => $departure->id,
            'code' => $reservationCode,
            'contact_name' => $traveler->name,
            'contact_email' => $traveler->email,
            'contact_phone' => '999999999',
            'total_amount' => 95,
            'status' => $status,
            'sales_channel' => 'web',
        ]);
        $reservationSeat = ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => $traveler->name,
            'document_number' => '12345678',
        ]);
        $ticket = null;
        if ($ticketCode !== null) {
            $ticket = Ticket::create([
                'reservation_seat_id' => $reservationSeat->id,
                'code' => $ticketCode,
                'boarding_token' => 'BRD-'.$suffix,
                'status' => 'confirmed',
                'issued_at' => now(),
            ]);
        }

        return ['reservation' => $reservation, 'ticket' => $ticket, 'company' => $company];
    }

    private function claim(string $email, string $code, string $token, bool $answered = false): ConsumerClaim
    {
        return ConsumerClaim::create([
            'provider' => 'platform',
            'provider_key' => 'navegaya-'.$code,
            'series_year' => 2026,
            'sequence' => abs(crc32($code)) % 100000,
            'code' => $code,
            'public_token' => $token,
            'filed_on' => '2026-11-20',
            'due_on' => '2026-12-15',
            'consumer_name' => 'Viajero',
            'document_type' => 'dni',
            'document_number' => '12345678',
            'address' => 'Iquitos',
            'phone' => '999111222',
            'email' => $email,
            'is_minor' => false,
            'good_kind' => 'service',
            'amount' => 95,
            'good_description' => 'Pasaje fluvial',
            'claim_type' => 'reclamo',
            'detail' => 'El zarpe se retrasó.',
            'request_text' => 'Quiero una explicación.',
            'email_accepted' => true,
            'responded_at' => $answered ? now() : null,
        ]);
    }
}
