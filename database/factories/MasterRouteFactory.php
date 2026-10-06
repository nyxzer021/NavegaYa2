<?php

namespace Database\Factories;

use App\Models\MasterRoute;
use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MasterRoute> */
class MasterRouteFactory extends Factory
{
    public function definition(): array
    {
        $origin = Port::create(['name' => 'Puerto '.$this->faker->unique()->city(), 'city' => $this->faker->unique()->city(), 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Muelle '.$this->faker->unique()->city(), 'city' => $this->faker->unique()->city(), 'region' => 'Loreto', 'is_active' => true]);

        return [
            'code' => 'TRM-'.strtoupper($this->faker->unique()->bothify('???-###')),
            'modality' => 'fluvial',
            'origin_city' => $origin->city,
            'destination_city' => $destination->city,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'river_basin' => 'Río Amazonas',
            'corridor' => 'Corredor Loreto',
            'estimated_duration_text' => '1 h 45 min',
            'status' => 'active',
        ];
    }
}
