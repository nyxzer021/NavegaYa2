<?php

namespace Database\Seeders;

use App\Models\AirRoute;
use App\Models\MasterRoute;
use App\Models\Port;
use App\Models\TransportRoute;
use Illuminate\Database\Seeder;

class LoretoGeographySeeder extends Seeder
{
    public function run(): void
    {
        $ports = collect([
            ['key' => 'iquitos-river', 'name' => 'Terminal Fluvial de Iquitos', 'city' => 'Iquitos', 'modality' => 'fluvial', 'port_type' => 'terminal', 'river' => 'Amazonas', 'latitude' => -3.7412000, 'longitude' => -73.2482000],
            ['key' => 'nauta-river', 'name' => 'Puerto de Nauta', 'city' => 'Nauta', 'modality' => 'fluvial', 'port_type' => 'terminal', 'river' => 'Marañón', 'latitude' => -4.5071000, 'longitude' => -73.5757000],
            ['key' => 'mazan-river', 'name' => 'Embarcadero de Mazán', 'city' => 'Mazán', 'modality' => 'fluvial', 'port_type' => 'embarcadero', 'river' => 'Napo', 'latitude' => -3.4968000, 'longitude' => -73.0920000],
            ['key' => 'requena-river', 'name' => 'Muelle Principal de Requena', 'city' => 'Requena', 'modality' => 'fluvial', 'port_type' => 'muelle', 'river' => 'Ucayali', 'latitude' => -5.0632000, 'longitude' => -73.8539000],
            ['key' => 'caballococha-river', 'name' => 'Puerto de Caballococha', 'city' => 'Caballococha', 'modality' => 'fluvial', 'port_type' => 'terminal', 'river' => 'Amazonas', 'latitude' => -3.9058000, 'longitude' => -70.5168000],
            ['key' => 'yurimaguas-river', 'name' => 'Puerto de Yurimaguas', 'city' => 'Yurimaguas', 'modality' => 'fluvial', 'port_type' => 'terminal', 'river' => 'Huallaga', 'latitude' => -5.8961000, 'longitude' => -76.1040000],
            ['key' => 'iquitos-air', 'name' => 'Aeropuerto Internacional de Iquitos', 'city' => 'Iquitos', 'modality' => 'aereo', 'port_type' => 'aeropuerto', 'river' => null, 'latitude' => -3.7847000, 'longitude' => -73.3088000],
            ['key' => 'contamana-air', 'name' => 'Aeródromo de Contamana', 'city' => 'Contamana', 'modality' => 'aereo', 'port_type' => 'aerodromo', 'river' => null, 'latitude' => -7.3333000, 'longitude' => -75.0064000],
            ['key' => 'san-lorenzo-air', 'name' => 'Aeródromo de San Lorenzo', 'city' => 'San Lorenzo', 'modality' => 'aereo', 'port_type' => 'aerodromo', 'river' => null, 'latitude' => -4.8294000, 'longitude' => -76.5558000],
            ['key' => 'caballococha-air', 'name' => 'Aeródromo de Caballococha', 'city' => 'Caballococha', 'modality' => 'aereo', 'port_type' => 'aerodromo', 'river' => null, 'latitude' => -3.9169000, 'longitude' => -70.5080000],
        ])->mapWithKeys(function (array $port): array {
            $key = $port['key'];
            unset($port['key']);

            $model = Port::query()->updateOrCreate(
                ['name' => $port['name'], 'city' => $port['city']],
                [...$port, 'region' => 'Loreto', 'is_active' => true]
            );

            return [$key => $model];
        });

        $routes = [
            ['TRM-IQT-NAU', 'fluvial', 'iquitos-river', 'nauta-river', 'Río Amazonas / Marañón', 'Corredor Iquitos–Nauta', '1 h 45 min', [[-73.2482, -3.7412], [-73.2920, -3.8010], [-73.3430, -3.8720], [-73.4010, -3.9690], [-73.4550, -4.0910], [-73.5060, -4.2300], [-73.5510, -4.3670], [-73.5757, -4.5071]]],
            ['TRM-IQT-MAZ', 'fluvial', 'iquitos-river', 'mazan-river', 'Río Amazonas / Napo', 'Corredor Iquitos–Mazán', '2 h 30 min', [[-73.2482, -3.7412], [-73.1910, -3.6960], [-73.1510, -3.6320], [-73.1220, -3.5660], [-73.0920, -3.4968]]],
            ['TRM-IQT-REQ', 'fluvial', 'iquitos-river', 'requena-river', 'Río Amazonas / Ucayali', 'Corredor Iquitos–Requena', '10 h', [[-73.2482, -3.7412], [-73.3690, -3.9210], [-73.4870, -4.1290], [-73.5980, -4.3440], [-73.6940, -4.5710], [-73.7770, -4.8120], [-73.8539, -5.0632]]],
            ['TRM-IQT-CAB', 'fluvial', 'iquitos-river', 'caballococha-river', 'Río Amazonas', 'Corredor fronterizo del Amazonas', '18 h', [[-73.2482, -3.7412], [-72.8240, -3.7560], [-72.3450, -3.7310], [-71.8670, -3.7890], [-71.3920, -3.8420], [-70.9250, -3.8810], [-70.5168, -3.9058]]],
            ['AIR-IQT-CTM', 'aereo', 'iquitos-air', 'contamana-air', null, 'Conexión aérea regional', '1 h 10 min', [[-73.3088, -3.7847], [-75.0064, -7.3333]]],
            ['AIR-IQT-SLZ', 'aereo', 'iquitos-air', 'san-lorenzo-air', null, 'Conexión aérea regional', '55 min', [[-73.3088, -3.7847], [-76.5558, -4.8294]]],
            ['AIR-IQT-CAB', 'aereo', 'iquitos-air', 'caballococha-air', null, 'Conexión aérea fronteriza', '1 h', [[-73.3088, -3.7847], [-70.5080, -3.9169]]],
        ];

        foreach ($routes as [$code, $modality, $originKey, $destinationKey, $basin, $corridor, $duration, $coordinates]) {
            $origin = $ports[$originKey];
            $destination = $ports[$destinationKey];

            $masterRoute = MasterRoute::query()
                ->where('modality', $modality)
                ->where('origin_port_id', $origin->id)
                ->where('destination_port_id', $destination->id)
                ->first()
                ?? MasterRoute::query()->where('code', $code)->first()
                ?? new MasterRoute;

            $masterRoute->fill([
                'code' => $code,
                'modality' => $modality,
                'origin_city' => $origin->city,
                'destination_city' => $destination->city,
                'origin_port_id' => $origin->id,
                'destination_port_id' => $destination->id,
                'river_basin' => $basin,
                'corridor' => $corridor,
                'estimated_duration_text' => $duration,
                'path_geojson' => ['type' => 'LineString', 'coordinates' => $coordinates],
                'status' => 'active',
            ])->save();
        }

        TransportRoute::query()->whereNull('master_route_id')->get()->each(function (TransportRoute $route): void {
            $route->update([
                'master_route_id' => MasterRoute::query()
                    ->where('modality', 'fluvial')
                    ->where('origin_port_id', $route->origin_port_id)
                    ->where('destination_port_id', $route->destination_port_id)
                    ->value('id'),
            ]);
        });

        AirRoute::query()->whereNull('master_route_id')->get()->each(function (AirRoute $route): void {
            $route->update([
                'master_route_id' => MasterRoute::query()
                    ->where('modality', 'aereo')
                    ->whereRaw('LOWER(origin_city) = ?', [mb_strtolower(trim($route->origin_city))])
                    ->whereRaw('LOWER(destination_city) = ?', [mb_strtolower(trim($route->destination_city))])
                    ->value('id'),
            ]);
        });
    }
}
