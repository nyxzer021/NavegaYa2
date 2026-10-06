<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\AircraftSeat;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirOperatorSeeder extends Seeder
{
    public function run(): void
    {
        $company = Organization::updateOrCreate(
            ['ruc' => '20609876541'],
            [
                'type' => 'transport_company',
                'modality' => 'aereo',
                'base_city' => 'Iquitos',
                'legal_name' => 'Aerotaxi Selva Central S.A.C.',
                'commercial_name' => 'Amazon Air Express',
                'contact_name' => 'Mesa de operaciones aéreas',
                'phone' => '965123456',
                'whatsapp' => '51965123456',
                'email' => 'operaciones@aerotaxiselva.pe',
                'address' => 'Aeropuerto Internacional Coronel FAP Francisco Secada Vignetta, Iquitos',
                'status' => 'active',
                'verified_at' => now(),
                'public_description' => 'Operador aéreo regional para vuelos programados y chárter hacia localidades de Loreto con acceso aéreo.',
            ]
        );

        // Repair demo records created before air transport had its own tenant.
        // Only the known Amazonía Express demo organization is affected.
        $legacyRiverCompany = Organization::where('ruc', '20987654321')->first();
        if ($legacyRiverCompany && $legacyRiverCompany->isNot($company)) {
            DB::transaction(function () use ($company, $legacyRiverCompany): void {
                $legacyAirRoutes = AirRoute::where('organization_id', $legacyRiverCompany->id)->pluck('id');
                $legacyAircraftIds = AirDeparture::whereIn('air_route_id', $legacyAirRoutes)
                    ->whereNotNull('aircraft_id')
                    ->pluck('aircraft_id');

                Aircraft::whereIn('id', $legacyAircraftIds)->update(['organization_id' => $company->id]);
                AirRoute::whereIn('id', $legacyAirRoutes)->update(['organization_id' => $company->id]);
            });
        }

        $aircraft = Aircraft::updateOrCreate(
            ['registration_number' => 'OB-2140'],
            [
                'organization_id' => $company->id,
                'name' => 'Amazon Air Caravan',
                'model' => 'Cessna 208B Grand Caravan',
                'seat_capacity' => 9,
                'status' => 'ready',
                'description' => 'Aeronave regional configurada para operaciones amazónicas y pistas de corta longitud.',
            ]
        );

        foreach (range(1, 3) as $row) {
            foreach (['A' => 1, 'B' => 2, 'C' => 3] as $letter => $column) {
                AircraftSeat::updateOrCreate(
                    ['aircraft_id' => $aircraft->id, 'code' => $row.$letter],
                    ['cabin' => 'Regional', 'row_position' => $row, 'column_position' => $column, 'is_available' => true]
                );
            }
        }

        $routes = [
            ['Iquitos', 'San Lorenzo', 'AIR-AMZ-IQT-SLZ', 50, 320],
            ['Iquitos', 'Colonia Angamos', 'AIR-AMZ-IQT-ANG', 45, 280],
        ];

        foreach ($routes as $routeIndex => [$origin, $destination, $code, $minutes, $fare]) {
            $route = AirRoute::updateOrCreate(
                ['code' => $code],
                [
                    'organization_id' => $company->id,
                    'origin_city' => $origin,
                    'destination_city' => $destination,
                    'estimated_duration_minutes' => $minutes,
                    'status' => 'active',
                    'description' => 'Conexión aérea regional sujeta a condiciones meteorológicas y autorización aeroportuaria.',
                ]
            );

            foreach ([1, 3, 5] as $offset) {
                $departureAt = now()->startOfDay()->addDays($offset)->setTime(8 + $routeIndex * 2, 30);
                AirDeparture::updateOrCreate(
                    ['air_route_id' => $route->id, 'departure_at' => $departureAt],
                    [
                        'aircraft_id' => $aircraft->id,
                        'boarding_starts_at' => $departureAt->copy()->subMinutes(45),
                        'estimated_arrival_at' => $departureAt->copy()->addMinutes($minutes),
                        'fare' => $fare,
                        'cargo_enabled' => true,
                        'status' => 'scheduled',
                        'notes' => 'Tarifa referencial. Presentarse con documento físico; salida sujeta a condiciones meteorológicas y autorización aeroportuaria.',
                    ]
                );
            }
        }
    }
}
