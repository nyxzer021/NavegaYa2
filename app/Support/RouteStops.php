<?php

namespace App\Support;

use App\Models\AirRoute;
use App\Models\MasterRoute;
use App\Models\Port;
use App\Models\RouteStop;
use App\Models\TransportRoute;
use Illuminate\Validation\ValidationException;

class RouteStops
{
    /**
     * Las escalas solo informan el recorrido. El pasaje sigue vendiéndose de origen a destino.
     */
    public static function label(TransportRoute|AirRoute $route): ?string
    {
        $stops = $route->relationLoaded('stops')
            ? $route->stops->sortBy('position')->values()
            : $route->stops()->with('port')->orderBy('position')->get();

        if ($stops->isEmpty()) {
            return null;
        }

        $parts = $stops->map(function (RouteStop $stop): string {
            $place = $stop->port->city ?: $stop->port->name;

            return $place.' '.MasterRoute::formatDuration((int) $stop->minutes_from_origin);
        });

        return 'Escalas: '.$parts->join(', ');
    }

    /**
     * @param  list<array{port_id?: mixed, hours?: mixed, minutes?: mixed}>  $rows
     */
    public static function replace(TransportRoute|AirRoute $route, array $rows, int $duration, int $originPortId, int $destinationPortId, string $side): void
    {
        $route->stops()->delete();
        $previous = 0;
        $position = 1;
        $used = [];

        foreach ($rows as $row) {
            if (blank($row['port_id'] ?? null)) {
                continue;
            }

            $port = Port::query()->find((int) $row['port_id']);
            $minutes = ((int) ($row['hours'] ?? 0) * 60) + (int) ($row['minutes'] ?? 0);
            if (! $port || ! $port->is_active || $port->publicSide() !== $side) {
                throw ValidationException::withMessages([
                    'stops' => 'La escala debe ser un puerto o aeródromo activo de la misma modalidad.',
                ]);
            }
            if (in_array($port->id, [$originPortId, $destinationPortId], true) || in_array($port->id, $used, true)) {
                throw ValidationException::withMessages([
                    'stops' => 'Una escala no puede repetir el origen, el destino ni otra escala.',
                ]);
            }
            if ($minutes < 1 || $minutes >= $duration || $minutes <= $previous) {
                throw ValidationException::withMessages([
                    'stops' => 'Cada escala debe quedar después de la anterior y antes de la llegada.',
                ]);
            }

            $used[] = $port->id;
            $previous = $minutes;
            $route->stops()->create([
                'port_id' => $port->id,
                'position' => $position,
                'minutes_from_origin' => $minutes,
            ]);
            $position++;
        }
    }
}
