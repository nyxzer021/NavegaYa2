<?php

namespace Database\Seeders;

use App\Models\AircraftSeat;
use App\Models\AirDeparture;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\RouteDeparture;
use App\Models\Ticket;
use App\Models\VesselSeat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketplaceDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DemoScheduleSeeder::class, RegionalSupervisionSeeder::class]);

        $amazon = Organization::where('ruc', '20987654321')->firstOrFail();
        $amazonRoute = $amazon->transportRoutes()
            ->whereHas('originPort', fn ($query) => $query->where('city', 'Nauta'))
            ->whereHas('destinationPort', fn ($query) => $query->where('city', 'Yurimaguas'))
            ->firstOrFail();
        $amazonVessel = $amazon->vessels()->firstOrFail();
        $amazonDepartureAt = today()->setTime(18, 0);
        $amazonDeparture = RouteDeparture::updateOrCreate(
            ['transport_route_id' => $amazonRoute->id, 'departure_at' => $amazonDepartureAt],
            ['vessel_id' => $amazonVessel->id, 'boarding_starts_at' => $amazonDepartureAt->copy()->subMinutes(45), 'estimated_arrival_at' => $amazonDepartureAt->copy()->addHours(18), 'fare' => 140, 'status' => 'scheduled']
        );
        $amazonSeat = VesselSeat::firstOrCreate(
            ['vessel_id' => $amazonVessel->id, 'code' => '1A'],
            ['deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1, 'is_available' => true]
        );

        $marañon = Organization::where('ruc', '20556677889')->firstOrFail();
        $marañonDeparture = RouteDeparture::whereHas('transportRoute', fn ($query) => $query->where('organization_id', $marañon->id))
            ->whereDate('departure_at', today())
            ->firstOrFail();
        $marañonSeat = VesselSeat::firstOrCreate(
            ['vessel_id' => $marañonDeparture->vessel_id, 'code' => '1A'],
            ['deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1, 'is_available' => true]
        );

        $selvaAir = Organization::where('ruc', '20443322110')->firstOrFail();
        $airDeparture = AirDeparture::whereHas('airRoute', fn ($query) => $query->where('organization_id', $selvaAir->id))
            ->whereDate('departure_at', today())
            ->firstOrFail();
        $airSeat = AircraftSeat::firstOrCreate(
            ['aircraft_id' => $airDeparture->aircraft_id, 'code' => '1A'],
            ['cabin' => 'Económica', 'row_position' => 1, 'column_position' => 1, 'is_available' => true]
        );

        $this->paidRiverReservation('RES-7801', 'TK-7801', 'Carlos Mendoza', '70451236', 140, 11.20, $amazonDeparture, $amazonSeat);
        $this->paidRiverReservation('RES-7802', 'TK-7802', 'Elena Valderrama', '41826375', 70, 5.60, $marañonDeparture, $marañonSeat);
        $this->paidAirReservation('RES-7803', 'TK-7803', 'Patrick Meyer (Turista)', 'DEU-PM7803', 280, 22.40, $airDeparture, $airSeat);

        Organization::updateOrCreate(
            ['ruc' => '20608912341'],
            [
                'type' => 'transport_company',
                'modality' => 'fluvial',
                'base_city' => 'Iquitos',
                'legal_name' => 'Turismo Aventura Amazonas EIRL',
                'commercial_name' => 'Turismo Aventura Amazonas',
                'contact_name' => 'Jorge Arévalo',
                'email' => 'jorge@turismoaventura.test',
                'phone' => '965890321',
                'status' => 'pending_verification',
                'commission_rate' => 8,
            ]
        );
    }

    private function paidRiverReservation(string $reservationCode, string $ticketCode, string $passenger, string $document, float $total, float $commission, RouteDeparture $departure, VesselSeat $seat): void
    {
        $reservation = Reservation::updateOrCreate(
            ['code' => $reservationCode],
            ['route_departure_id' => $departure->id, 'air_departure_id' => null, 'contact_name' => $passenger, 'contact_email' => Str::slug($passenger).'@example.test', 'contact_phone' => '999000000', 'contact_document' => $document, 'total_amount' => $total, 'status' => 'confirmed', 'sales_channel' => 'web', 'payment_method' => 'culqi', 'expires_at' => null, 'paid_at' => now()]
        );
        $reservationSeat = ReservationSeat::updateOrCreate(
            ['reservation_id' => $reservation->id],
            ['route_departure_id' => $departure->id, 'air_departure_id' => null, 'vessel_seat_id' => $seat->id, 'aircraft_seat_id' => null, 'passenger_name' => $passenger, 'document_type' => 'DNI', 'document_number' => $document, 'passenger_age' => 34]
        );
        $this->paymentAndTicket($reservation, $reservationSeat, $ticketCode, $total, $commission);
    }

    private function paidAirReservation(string $reservationCode, string $ticketCode, string $passenger, string $document, float $total, float $commission, AirDeparture $departure, AircraftSeat $seat): void
    {
        $reservation = Reservation::updateOrCreate(
            ['code' => $reservationCode],
            ['route_departure_id' => null, 'air_departure_id' => $departure->id, 'contact_name' => $passenger, 'contact_email' => 'patrick.meyer@example.test', 'contact_phone' => '999000003', 'contact_document' => $document, 'total_amount' => $total, 'status' => 'confirmed', 'sales_channel' => 'web', 'payment_method' => 'culqi', 'expires_at' => null, 'paid_at' => now()]
        );
        $reservationSeat = ReservationSeat::updateOrCreate(
            ['reservation_id' => $reservation->id],
            ['route_departure_id' => null, 'air_departure_id' => $departure->id, 'vessel_seat_id' => null, 'aircraft_seat_id' => $seat->id, 'passenger_name' => $passenger, 'document_type' => 'Pasaporte', 'document_number' => $document, 'passenger_age' => 41]
        );
        $this->paymentAndTicket($reservation, $reservationSeat, $ticketCode, $total, $commission);
    }

    private function paymentAndTicket(Reservation $reservation, ReservationSeat $reservationSeat, string $ticketCode, float $total, float $commission): void
    {
        Payment::updateOrCreate(
            ['provider_reference' => 'culqi-demo-'.strtolower($ticketCode)],
            ['reservation_id' => $reservation->id, 'method' => 'card', 'provider' => 'culqi', 'amount' => $total, 'currency_code' => 'PEN', 'commission_amount' => $commission, 'operator_net' => $total - $commission, 'status' => 'succeeded', 'paid_at' => now(), 'provider_payload' => ['source' => 'marketplace_dashboard_demo']]
        );
        Ticket::updateOrCreate(
            ['code' => $ticketCode],
            ['reservation_seat_id' => $reservationSeat->id, 'boarding_token' => hash('sha256', 'navegaya-'.$ticketCode), 'status' => 'confirmed', 'issued_at' => now()]
        );
    }
}
