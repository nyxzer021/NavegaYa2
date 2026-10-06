<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('origin_port_id')->constrained('ports')->restrictOnDelete();
            $table->foreignId('destination_port_id')->constrained('ports')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('route_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_id')->constrained()->restrictOnDelete();
            $table->timestamp('departure_at');
            $table->timestamp('boarding_starts_at')->nullable();
            $table->timestamp('estimated_arrival_at')->nullable();
            $table->decimal('fare', 10, 2);
            $table->boolean('cargo_enabled')->default(false);
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['transport_route_id', 'departure_at']);
            $table->index(['vessel_id', 'departure_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_departures');
        Schema::dropIfExists('transport_routes');
    }
};
