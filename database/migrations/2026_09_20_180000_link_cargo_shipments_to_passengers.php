<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_shipments', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->after('air_departure_id')->constrained()->nullOnDelete();
            $table->foreignId('reservation_seat_id')->nullable()->after('reservation_id')->constrained()->nullOnDelete();
            $table->index('reservation_seat_id');
        });
    }

    public function down(): void
    {
        Schema::table('cargo_shipments', function (Blueprint $table) {
            $table->dropIndex(['reservation_seat_id']);
            $table->dropConstrainedForeignId('reservation_seat_id');
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
