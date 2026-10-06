<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('air_routes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('origin_city');
            $t->string('destination_city');
            $t->string('code', 30)->unique();
            $t->unsignedSmallInteger('estimated_duration_minutes')->nullable();
            $t->string('status', 20)->default('active');
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('air_departures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('air_route_id')->constrained()->cascadeOnDelete();
            $t->foreignId('aircraft_id')->constrained()->restrictOnDelete();
            $t->timestamp('departure_at');
            $t->timestamp('boarding_starts_at')->nullable();
            $t->timestamp('estimated_arrival_at')->nullable();
            $t->decimal('fare', 10, 2);
            $t->boolean('cargo_enabled')->default(false);
            $t->string('status', 20)->default('scheduled');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::table('cargo_shipments', function (Blueprint $t) {
            $t->foreignId('air_departure_id')->nullable()->after('route_departure_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cargo_shipments', fn (Blueprint $t) => $t->dropConstrainedForeignId('air_departure_id'));
        Schema::dropIfExists('air_departures');
        Schema::dropIfExists('air_routes');
    }
};
