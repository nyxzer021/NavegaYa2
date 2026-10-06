<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_departure_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('contact_name', 150);
            $table->string('contact_email', 150);
            $table->string('contact_phone', 40);
            $table->decimal('total_amount', 10, 2);
            $table->string('status', 25)->default('pending_payment');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['route_departure_id', 'status']);
        });

        Schema::create('reservation_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_departure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_seat_id')->constrained()->restrictOnDelete();
            $table->string('passenger_name', 150);
            $table->string('document_number', 40);
            $table->timestamps();
            $table->unique(['route_departure_id', 'vessel_seat_id'], 'departure_seat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_seats');
        Schema::dropIfExists('reservations');
    }
};
