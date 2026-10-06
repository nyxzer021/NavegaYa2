<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_routes', function (Blueprint $table) {
            $table->foreignId('master_route_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        Schema::table('air_routes', function (Blueprint $table) {
            $table->foreignId('master_route_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });

        DB::table('transport_routes')->orderBy('id')->get()->each(function (object $route): void {
            $origin = DB::table('ports')->find($route->origin_port_id);
            $destination = DB::table('ports')->find($route->destination_port_id);
            if (! $origin || ! $destination || mb_strtolower(trim($origin->city)) === mb_strtolower(trim($destination->city))) {
                return;
            }
            $masterId = DB::table('master_routes')->where('modality', 'fluvial')
                ->where('origin_port_id', $route->origin_port_id)->where('destination_port_id', $route->destination_port_id)->value('id');
            if (! $masterId) {
                $masterId = DB::table('master_routes')->insertGetId([
                    'code' => 'TRM-F-'.$route->origin_port_id.'-'.$route->destination_port_id,
                    'modality' => 'fluvial', 'origin_city' => $origin->city, 'destination_city' => $destination->city,
                    'origin_port_id' => $route->origin_port_id, 'destination_port_id' => $route->destination_port_id,
                    'estimated_duration_text' => $route->estimated_duration_minutes ? intdiv($route->estimated_duration_minutes, 60).' h '.($route->estimated_duration_minutes % 60).' min' : null,
                    'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('transport_routes')->where('id', $route->id)->update(['master_route_id' => $masterId]);
        });

        DB::table('air_routes')->orderBy('id')->get()->each(function (object $route): void {
            $origin = DB::table('ports')->whereRaw('LOWER(city) = ?', [mb_strtolower($route->origin_city)])->first();
            $destination = DB::table('ports')->whereRaw('LOWER(city) = ?', [mb_strtolower($route->destination_city)])->first();
            if (! $origin || ! $destination || $origin->id === $destination->id) {
                return;
            }
            $masterId = DB::table('master_routes')->where('modality', 'aereo')->where('origin_port_id', $origin->id)->where('destination_port_id', $destination->id)->value('id');
            if (! $masterId) {
                $masterId = DB::table('master_routes')->insertGetId([
                    'code' => 'TRM-A-'.$origin->id.'-'.$destination->id,
                    'modality' => 'aereo', 'origin_city' => $route->origin_city, 'destination_city' => $route->destination_city,
                    'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
                    'estimated_duration_text' => $route->estimated_duration_minutes ? intdiv($route->estimated_duration_minutes, 60).' h '.($route->estimated_duration_minutes % 60).' min' : null,
                    'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('air_routes')->where('id', $route->id)->update(['master_route_id' => $masterId]);
        });
    }

    public function down(): void
    {
        Schema::table('air_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_route_id');
        });

        Schema::table('transport_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_route_id');
        });
    }
};
