<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->foreignId('air_departure_id')->nullable()->after('route_departure_id')->constrained()->nullOnDelete();
            $t->index(['air_departure_id', 'status']);
        });
        Schema::table('reservation_seats', function (Blueprint $t) {
            $t->foreignId('air_departure_id')->nullable()->after('route_departure_id')->constrained()->nullOnDelete();
            $t->foreignId('aircraft_seat_id')->nullable()->after('vessel_seat_id')->constrained()->nullOnDelete();
            $t->index(['air_departure_id', 'aircraft_seat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reservation_seats', function (Blueprint $t) {
            $t->dropConstrainedForeignId('aircraft_seat_id');
            $t->dropConstrainedForeignId('air_departure_id');
        });
        Schema::table('reservations', fn (Blueprint $t) => $t->dropConstrainedForeignId('air_departure_id'));
    }
};
