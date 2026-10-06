<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_routes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('modality', 20)->index();
            $table->string('origin_city');
            $table->string('destination_city');
            $table->foreignId('origin_port_id')->constrained('ports')->restrictOnDelete();
            $table->foreignId('destination_port_id')->constrained('ports')->restrictOnDelete();
            $table->string('river_basin')->nullable();
            $table->string('corridor')->nullable();
            $table->string('estimated_duration_text', 80)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();

            $table->unique(['modality', 'origin_port_id', 'destination_port_id'], 'master_routes_unique_segment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_routes');
    }
};
