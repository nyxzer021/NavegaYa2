<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $invalidRouteIds = DB::table('air_routes')
                ->where(function ($query) {
                    $query->whereRaw('LOWER(TRIM(origin_city)) = LOWER(TRIM(destination_city))')
                        ->orWhereNull('master_route_id');
                })
                ->pluck('id');

            if ($invalidRouteIds->isEmpty()) {
                return;
            }

            $invalidDepartureIds = DB::table('air_departures')
                ->whereIn('air_route_id', $invalidRouteIds)
                ->pluck('id');

            if ($invalidDepartureIds->isNotEmpty()) {
                DB::table('cargo_shipments')
                    ->whereIn('air_departure_id', $invalidDepartureIds)
                    ->update(['air_departure_id' => null, 'updated_at' => now()]);
                DB::table('air_departures')->whereIn('id', $invalidDepartureIds)->delete();
            }

            DB::table('air_routes')->whereIn('id', $invalidRouteIds)->delete();
        });
    }

    public function down(): void
    {
        // La limpieza de datos corruptos es intencionalmente irreversible.
    }
};
