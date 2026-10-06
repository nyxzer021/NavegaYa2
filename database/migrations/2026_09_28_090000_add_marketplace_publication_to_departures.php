<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_departures', function (Blueprint $table): void {
            $table->boolean('is_published')->default(true)->after('status')->index();
        });
        Schema::table('air_departures', function (Blueprint $table): void {
            $table->boolean('is_published')->default(true)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('route_departures', fn (Blueprint $table) => $table->dropColumn('is_published'));
        Schema::table('air_departures', fn (Blueprint $table) => $table->dropColumn('is_published'));
    }
};
