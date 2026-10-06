<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use App\Models\AirDeparture;
use App\Models\AirRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class RegionalSupervisionSeeder extends Seeder
{
    public function run(): void
    {
        $marañon = Organization::updateOrCreate(
            ['ruc' => '20556677889'],
            ['type' => 'transport_company', 'legal_name' => 'Expreso Fluvial Marañón SAC', 'commercial_name' => 'Expreso Fluvial Marañón', 'modality' => 'fluvial', 'base_city' => 'Nauta', 'email' => 'operaciones@expresomaranon.test', 'phone' => '965667788', 'status' => 'active', 'verified_at' => now()]
        );
        $nauta = Port::updateOrCreate(
            ['name' => 'Terminal Fluvial de Nauta'],
            ['modality' => 'fluvial', 'port_type' => 'terminal', 'city' => 'Nauta', 'region' => 'Loreto', 'river' => 'Marañón', 'is_active' => true]
        );
        $requena = Port::updateOrCreate(
            ['name' => 'Muelle Principal de Requena'],
            ['modality' => 'fluvial', 'port_type' => 'muelle', 'city' => 'Requena', 'region' => 'Loreto', 'river' => 'Ucayali', 'is_active' => true]
        );
        $vessel = Vessel::updateOrCreate(
            ['registration_number' => 'PA-MAR-2201'],
            ['organization_id' => $marañon->id, 'name' => 'Rápido Marañón II', 'vessel_type' => 'Rápida', 'seat_capacity' => 28, 'crew_capacity' => 3, 'status' => 'ready']
        );
        $riverRoute = TransportRoute::updateOrCreate(
            ['organization_id' => $marañon->id, 'origin_port_id' => $nauta->id, 'destination_port_id' => $requena->id],
            ['estimated_duration_minutes' => 300, 'status' => 'active', 'description' => 'Servicio regional Nauta–Requena.']
        );
        $riverDeparture = today()->setTime(14, 30);
        RouteDeparture::updateOrCreate(
            ['transport_route_id' => $riverRoute->id, 'departure_at' => $riverDeparture],
            ['vessel_id' => $vessel->id, 'boarding_starts_at' => $riverDeparture->copy()->subMinutes(45), 'estimated_arrival_at' => $riverDeparture->copy()->addHours(5), 'fare' => 125, 'status' => 'scheduled']
        );

        $selvaAir = Organization::updateOrCreate(
            ['ruc' => '20443322110'],
            ['type' => 'transport_company', 'legal_name' => 'Selva Air Taxi EIRL', 'commercial_name' => 'Selva Air Taxi', 'modality' => 'aereo', 'base_city' => 'Iquitos', 'email' => 'operaciones@selvaair.test', 'phone' => '964433221', 'status' => 'active', 'verified_at' => now()]
        );
        $aircraft = Aircraft::updateOrCreate(
            ['registration_number' => 'OB-2198'],
            ['organization_id' => $selvaAir->id, 'name' => 'Selva Caravan I', 'model' => 'Cessna 208B', 'seat_capacity' => 9, 'status' => 'ready']
        );
        $airRoute = AirRoute::updateOrCreate(
            ['code' => 'AIR-SAT-IQT-CTM'],
            ['organization_id' => $selvaAir->id, 'origin_city' => 'Iquitos', 'destination_city' => 'Contamana', 'estimated_duration_minutes' => 70, 'status' => 'active']
        );
        $airDeparture = today()->setTime(10, 15);
        AirDeparture::updateOrCreate(
            ['air_route_id' => $airRoute->id, 'departure_at' => $airDeparture],
            ['aircraft_id' => $aircraft->id, 'boarding_starts_at' => $airDeparture->copy()->subMinutes(45), 'estimated_arrival_at' => $airDeparture->copy()->addMinutes(70), 'fare' => 310, 'status' => 'scheduled']
        );
    }
}
