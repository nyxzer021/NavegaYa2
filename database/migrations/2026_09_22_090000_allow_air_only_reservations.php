<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->foreignId('route_departure_id')->nullable()->change());
        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->foreignId('route_departure_id')->nullable()->change();
            $table->foreignId('vessel_seat_id')->nullable()->change();
            $table->unique(['air_departure_id', 'aircraft_seat_id'], 'air_departure_seat_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->dropUnique('air_departure_seat_unique');
            $table->foreignId('vessel_seat_id')->nullable(false)->change();
            $table->foreignId('route_departure_id')->nullable(false)->change();
        });
        Schema::table('reservations', fn (Blueprint $table) => $table->foreignId('route_departure_id')->nullable(false)->change());
    }
};
