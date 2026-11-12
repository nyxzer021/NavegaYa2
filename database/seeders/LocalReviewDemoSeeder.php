<?php

namespace Database\Seeders;

use App\Models\DepartureSeries;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Support\DepartureSchedule;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalReviewDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalReviewDemoSeeder solo puede correr cuando APP_ENV=local.');
        }

        $company = Organization::query()->where('email', 'revision-visual@navegaya.test')->first()
            ?? Organization::query()->where('commercial_name', 'Río Revisión')->first();
        if ($company === null) {
            throw new RuntimeException('No está la empresa Río Revisión en esta base local.');
        }

        $route = TransportRoute::query()
            ->where('organization_id', $company->id)
            ->whereHas('originPort', fn ($query) => $query->where('city', 'Iquitos'))
            ->whereHas('destinationPort', fn ($query) => $query->where('city', 'Nauta'))
            ->first();
        if ($route === null) {
            throw new RuntimeException('Río Revisión no tiene la ruta Iquitos → Nauta.');
        }

        $iquitos = $route->originPort;
        $nauta = $route->destinationPort;
        $yurimaguas = Port::query()->firstOrCreate(
            ['name' => 'Puerto de Yurimaguas'],
            ['city' => 'Yurimaguas', 'region' => 'Loreto', 'river' => 'Huallaga', 'modality' => 'fluvial', 'is_active' => true]
        );

        $toNauta = $this->segment($iquitos, $nauta, 'TRM-IQT-NAU', 150, 'Río Amazonas / Marañón');
        $this->segment($nauta, $yurimaguas, 'TRM-NAU-YUR', 1080, 'Río Marañón / Huallaga');
        $route->update(['master_route_id' => $toNauta->id]);

        $vessel = Vessel::query()->firstOrCreate(
            ['registration_number' => 'LAN-DEMO-01'],
            [
                'organization_id' => $company->id,
                'base_port_id' => $iquitos->id,
                'name' => 'Lancha Demo',
                'vessel_type' => 'lancha',
                'seat_capacity' => 24,
                'life_vest_count' => 24,
                'insurance_policy' => 'POL-DEMO-2026',
                'insurance_expires_at' => now()->addYear()->toDateString(),
                'inspection_expires_at' => now()->addYear()->toDateString(),
                'status' => 'ready',
            ]
        );
        $vessel->update([
            'organization_id' => $company->id,
            'name' => 'Lancha Demo',
            'seat_capacity' => 24,
            'life_vest_count' => 24,
            'insurance_policy' => 'POL-DEMO-2026',
            'insurance_expires_at' => now()->addYear()->toDateString(),
            'inspection_expires_at' => now()->addYear()->toDateString(),
            'status' => 'ready',
        ]);
        $this->places($vessel);

        $starts = now()->timezone(DepartureSchedule::ZONE)->toDateString();
        $ends = now()->timezone(DepartureSchedule::ZONE)->addDays(29)->toDateString();
        $series = DepartureSeries::query()->firstOrCreate(
            ['organization_id' => $company->id, 'vessel_id' => $vessel->id, 'notes' => 'Serie de demostración local.'],
            [
                'transport_route_id' => $route->id,
                'weekdays' => [1, 3, 5],
                'departure_time' => '08:00',
                'starts_on' => $starts,
                'ends_on' => $ends,
                'boarding_offset_minutes' => 45,
                'fare' => 80,
                'cargo_enabled' => false,
                'status' => 'active',
            ]
        );

        $created = 0;
        foreach (DepartureSchedule::dates($series->starts_on->toDateString(), $series->ends_on->toDateString(), $series->weekdays, $series->departure_time) as $moment) {
            $already = RouteDeparture::query()
                ->where('departure_series_id', $series->id)
                ->where('departure_at', $moment)
                ->exists();
            RouteDeparture::query()->firstOrCreate(
                ['departure_series_id' => $series->id, 'departure_at' => $moment],
                [
                    'transport_route_id' => $route->id,
                    'vessel_id' => $vessel->id,
                    'boarding_starts_at' => $moment->copy()->subMinutes(45),
                    'estimated_arrival_at' => $moment->copy()->addMinutes(150),
                    'fare' => 80,
                    'cargo_enabled' => false,
                    'included_baggage_kg' => 25,
                    'cargo_payment_location' => 'terminal',
                    'status' => 'scheduled',
                    'is_published' => true,
                ]
            );
            if (! $already) {
                $created++;
            }
        }

        $sold = $series->riverDepartures()->where('departure_at', '>=', now())->orderBy('departure_at')->first();
        $traveler = $this->traveler();
        if ($sold) {
            foreach ([
                ['DEMO-PAGO-01', 'Ana Demo', '70000001', '1A'],
                ['DEMO-PAGO-02', 'Luis Demo', '70000002', '1B'],
            ] as [$code, $name, $document, $seatCode]) {
                $reservation = Reservation::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'user_id' => $traveler->id,
                        'route_departure_id' => $sold->id,
                        'contact_name' => $name,
                        'contact_email' => $traveler->email,
                        'contact_phone' => '999000111',
                        'total_amount' => 80,
                        'status' => 'confirmed',
                        'sales_channel' => 'web',
                        'paid_at' => now(),
                    ]
                );
                $seat = VesselSeat::query()->where('vessel_id', $vessel->id)->where('code', $seatCode)->first();
                if ($seat) {
                    ReservationSeat::query()->firstOrCreate(
                        ['reservation_id' => $reservation->id, 'vessel_seat_id' => $seat->id],
                        [
                            'route_departure_id' => $sold->id,
                            'passenger_name' => $name,
                            'document_type' => 'DNI',
                            'document_number' => $document,
                        ]
                    );
                }
            }
        }

        $this->command?->info('Datos locales listos. Viajero: viajero-demo@navegaya.test / password. Salidas nuevas de la serie: '.$created.'.');
    }

    private function segment(Port $origin, Port $destination, string $code, int $minutes, string $basin): MasterRoute
    {
        $segment = MasterRoute::query()->firstOrNew([
            'modality' => 'fluvial',
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
        ]);
        $segment->fill([
            'code' => $segment->code ?: $code,
            'origin_city' => $origin->city,
            'destination_city' => $destination->city,
            'river_basin' => $basin,
            'corridor' => 'Corredor Loreto',
            'estimated_duration_text' => MasterRoute::formatDuration($minutes),
            'estimated_duration_minutes' => $minutes,
            'status' => 'active',
            'duration_needs_review' => false,
        ])->save();

        return $segment;
    }

    private function places(Vessel $vessel): void
    {
        $column = 1;
        foreach (range(1, 4) as $row) {
            foreach (['A', 'B', 'C', 'D'] as $letter) {
                VesselSeat::query()->firstOrCreate(
                    ['vessel_id' => $vessel->id, 'code' => $row.$letter],
                    [
                        'deck' => 'principal',
                        'seat_class' => 'standard',
                        'space_type' => 'seat',
                        'row_position' => $row,
                        'column_position' => $column,
                        'is_available' => true,
                    ]
                );
                $column++;
            }
            $column = 1;
        }
        foreach (range(1, 6) as $number) {
            VesselSeat::query()->firstOrCreate(
                ['vessel_id' => $vessel->id, 'code' => 'H-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT)],
                ['deck' => 'principal', 'seat_class' => 'hammock', 'space_type' => 'hammock', 'is_available' => true]
            );
        }
        VesselSeat::query()->firstOrCreate(
            ['vessel_id' => $vessel->id, 'code' => 'CAM-01'],
            [
                'deck' => 'principal',
                'seat_class' => 'cabin',
                'space_type' => 'cabin_bed',
                'cabin_code' => 'CAM-01',
                'cabin_sold_as' => 'whole',
                'bed_count' => 2,
                'is_available' => true,
            ]
        );
    }

    private function traveler(): User
    {
        $traveler = User::query()->firstOrCreate(
            ['email' => 'viajero-demo@navegaya.test'],
            ['name' => 'Viajero Demo', 'password' => 'password', 'email_verified_at' => now()]
        );
        $role = Role::query()->firstOrCreate(
            ['code' => 'customer'],
            ['name' => 'Cliente / pasajero', 'description' => 'Compra pasajes y consulta sus boletos.']
        );
        if (! $traveler->roles()->where('roles.id', $role->id)->exists()) {
            $traveler->roles()->attach($role->id);
        }

        return $traveler;
    }
}
