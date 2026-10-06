<?php

namespace Database\Seeders;

use App\Models\MasterRoute;
use App\Models\Port;
use Illuminate\Database\Seeder;

class MasterRouteSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            ['TRM-IQT-NAU', 'Iquitos', 'Nauta', 'Río Amazonas / Marañón', '1 h 45 min'],
            ['TRM-NAU-YUR', 'Nauta', 'Yurimaguas', 'Río Marañón / Huallaga', '18 h'],
        ];

        foreach ($segments as [$code, $originCity, $destinationCity, $basin, $duration]) {
            $origin = Port::query()->where('city', $originCity)->where('is_active', true)->first();
            $destination = Port::query()->where('city', $destinationCity)->where('is_active', true)->first();
            if (! $origin || ! $destination) {
                continue;
            }
            $masterRoute = MasterRoute::query()
                ->where('modality', 'fluvial')
                ->where('origin_port_id', $origin->id)
                ->where('destination_port_id', $destination->id)
                ->first()
                ?? MasterRoute::query()->where('code', $code)->first()
                ?? new MasterRoute;

            $masterRoute->fill([
                'code' => $code,
                'modality' => 'fluvial', 'origin_city' => $originCity, 'destination_city' => $destinationCity,
                'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
                'river_basin' => $basin, 'corridor' => 'Corredor Loreto',
                'estimated_duration_text' => $duration, 'status' => 'active',
            ])->save();
        }

        $nautaPort = Port::query()
            ->where('city', 'Nauta')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN name = 'Puerto de Nauta' THEN 0 ELSE 1 END")
            ->first();

        if ($nautaPort) {
            MasterRoute::query()->where('code', 'TRM-F-3-1')->update([
                'destination_city' => 'Nauta',
                'destination_port_id' => $nautaPort->id,
                'river_basin' => 'Río Huallaga / Marañón',
            ]);
        }
    }
}
