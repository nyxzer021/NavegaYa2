<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_shipments', fn (Blueprint $t) => $t->foreignId('route_departure_id')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('cargo_shipments', fn (Blueprint $t) => $t->foreignId('route_departure_id')->nullable(false)->change());
    }
};
