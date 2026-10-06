<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\AircraftSeat;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use App\Models\VesselSeat;
use Illuminate\Database\Seeder;

class DemoScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Organization::firstOrCreate(
            ['ruc' => '20987654321'],
            ['type' => 'transport_company', 'legal_name' => 'NavegaYA Demo Fluvial SAC', 'commercial_name' => 'Amazonía Express', 'email' => 'demo@navegaya.test', 'phone' => '999888777', 'whatsapp' => '51999888777', 'address' => 'Iquitos, Loreto', 'status' => 'active', 'public_description' => 'Operador de demostración para probar rutas, horarios, reservas y asientos.']
        );

        $ports = collect([
            'Iquitos' => ['name' => 'Puerto de Iquitos', 'river' => 'Amazonas'],
            'Nauta' => ['name' => 'Puerto de Nauta', 'river' => 'Marañón'],
            'Yurimaguas' => ['name' => 'Puerto de Yurimaguas', 'river' => 'Huallaga'],
            'San Lorenzo' => ['name' => 'Puerto de San Lorenzo', 'river' => 'Marañón'],
        ])->mapWithKeys(function (array $data, string $city) {
            $port = Port::firstOrCreate(['name' => $data['name']], ['city' => $city, 'region' => 'Loreto', 'river' => $data['river'], 'is_active' => true]);

            return [$city => $port];
        });

        $vessel = Vessel::firstOrCreate(
            ['registration_number' => 'NY-DEMO-01'],
            ['organization_id' => $company->id, 'name' => 'Amazonía Express I', 'vessel_type' => 'Rápido', 'seat_capacity' => 32, 'crew_capacity' => 4, 'status' => 'ready']
        );

        foreach (range(1, 8) as $row) {
            foreach (['A' => 1, 'B' => 2, 'C' => 4, 'D' => 5] as $letter => $column) {
                VesselSeat::firstOrCreate(
                    ['vessel_id' => $vessel->id, 'code' => $row.$letter],
                    ['deck' => 'principal', 'seat_class' => 'standard', 'row_position' => $row, 'column_position' => $column, 'is_available' => true]
                );
            }
        }
        $routes = [
            ['Iquitos', 'Nauta', 'IQT-NAU-DEMO', 105, 95],
            ['Nauta', 'Yurimaguas', 'NAU-YUR-DEMO', 1080, 140],
            ['Yurimaguas', 'Iquitos', 'YUR-IQT-DEMO', 1440, 160],
            ['San Lorenzo', 'Yurimaguas', 'SLZ-YUR-DEMO', 1200, 130],
        ];

        foreach ($routes as [$origin, $destination, $code, $minutes, $fare]) {
            if ($origin === $destination || $ports[$origin]->id === $ports[$destination]->id) {
                continue;
            }

            $masterRouteId = MasterRoute::query()
                ->where('modality', 'fluvial')
                ->where('origin_port_id', $ports[$origin]->id)
                ->where('destination_port_id', $ports[$destination]->id)
                ->value('id');
            $route = TransportRoute::updateOrCreate(
                ['code' => $code],
                ['organization_id' => $company->id, 'master_route_id' => $masterRouteId, 'origin_port_id' => $ports[$origin]->id, 'destination_port_id' => $ports[$destination]->id, 'estimated_duration_minutes' => $minutes, 'status' => 'active', 'description' => 'Ruta de prueba para NavegaYA.']
            );

            foreach ([[1, '08:00'], [1, '15:30'], [2, '07:30'], [2, '16:00'], [3, '09:00'], [4, '06:30']] as [$days, $time]) {
                $departureAt = now()->startOfDay()->addDays($days)->setTimeFromTimeString($time);
                RouteDeparture::firstOrCreate(
                    ['transport_route_id' => $route->id, 'departure_at' => $departureAt],
                    ['vessel_id' => $vessel->id, 'boarding_starts_at' => $departureAt->copy()->subMinutes(45), 'estimated_arrival_at' => $departureAt->copy()->addMinutes($minutes), 'fare' => $fare, 'cargo_enabled' => true, 'included_baggage_kg' => 25, 'status' => 'scheduled', 'notes' => 'Salida de prueba. Presentarse 45 minutos antes.']
                );
            }
        }

        $aircraft = Aircraft::firstOrCreate(
            ['registration_number' => 'OB-NYA-01'],
            ['organization_id' => $company->id, 'name' => 'Cessna Grand Caravan', 'model' => 'Cessna 208B', 'seat_capacity' => 9, 'status' => 'ready', 'description' => 'Aeronave de prueba para rutas amazónicas.']
        );
        foreach (range(1, 3) as $row) {
            foreach (['A' => 1, 'B' => 2, 'C' => 3] as $letter => $column) {
                AircraftSeat::firstOrCreate(
                    ['aircraft_id' => $aircraft->id, 'code' => $row.$letter],
                    ['cabin' => 'Económica', 'row_position' => $row, 'column_position' => $column, 'is_available' => true]
                );
            }
        }
        foreach ([['Iquitos', 'Contamana', 'AIR-IQT-CTM', 75, 280], ['Iquitos', 'San Lorenzo', 'AIR-IQT-SLZ', 65, 250]] as [$origin,$destination,$code,$minutes,$fare]) {
            if ($origin === $destination) {
                continue;
            }

            $masterRouteId = MasterRoute::query()
                ->where('modality', 'aereo')
                ->where('origin_city', $origin)
                ->where('destination_city', $destination)
                ->value('id');
            $route = AirRoute::updateOrCreate(
                ['code' => $code],
                ['organization_id' => $company->id, 'master_route_id' => $masterRouteId, 'origin_city' => $origin, 'destination_city' => $destination, 'estimated_duration_minutes' => $minutes, 'status' => 'active', 'description' => 'Ruta aérea amazónica de demostración.']
            );
            foreach ([[1, '10:30'], [2, '14:00'], [4, '08:15']] as [$days,$time]) {
                $departureAt = now()->startOfDay()->addDays($days)->setTimeFromTimeString($time);
                AirDeparture::firstOrCreate(
                    ['air_route_id' => $route->id, 'departure_at' => $departureAt],
                    ['aircraft_id' => $aircraft->id, 'boarding_starts_at' => $departureAt->copy()->subMinutes(40), 'estimated_arrival_at' => $departureAt->copy()->addMinutes($minutes), 'fare' => $fare, 'cargo_enabled' => true, 'status' => 'scheduled', 'notes' => 'Presentarse 60 minutos antes con documento de identidad.']
                );
            }
        }
    }
}
