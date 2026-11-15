<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plano visual por cubierta: tamaño de la cuadrícula y elementos que no se venden (baño, escalera, cabina de mando, carga, salida).
     * Los asientos siguen viviendo en vessel_seats.
     */
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->json('layout')->nullable()->after('seat_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
