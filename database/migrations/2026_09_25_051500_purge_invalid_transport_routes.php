<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $invalidRouteIds = DB::table('transport_routes as routes')
                ->join('ports as origins', 'origins.id', '=', 'routes.origin_port_id')
                ->join('ports as destinations', 'destinations.id', '=', 'routes.destination_port_id')
                ->where(function ($query) {
                    $query->whereColumn('routes.origin_port_id', 'routes.destination_port_id')
                        ->orWhereRaw('LOWER(TRIM(origins.city)) = LOWER(TRIM(destinations.city))')
                        ->orWhereNull('routes.master_route_id');
                })
                ->pluck('routes.id');

            if ($invalidRouteIds->isEmpty()) {
                return;
            }

            $invalidDepartureIds = DB::table('route_departures')
                ->whereIn('transport_route_id', $invalidRouteIds)
                ->pluck('id');

            if ($invalidDepartureIds->isNotEmpty()) {
                DB::table('cargo_shipments')
                    ->whereIn('route_departure_id', $invalidDepartureIds)
                    ->update(['route_departure_id' => null, 'updated_at' => now()]);
                DB::table('route_departures')->whereIn('id', $invalidDepartureIds)->delete();
            }

            DB::table('transport_routes')->whereIn('id', $invalidRouteIds)->delete();
        });
    }

    public function down(): void
    {
        // La limpieza de datos corruptos es intencionalmente irreversible.
    }
};
