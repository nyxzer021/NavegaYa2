<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->string('provider', 50)->default('sandbox');
            $table->string('provider_reference', 100)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('status', 25)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
            $table->index(['reservation_id', 'status']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_seat_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('boarding_token', 80)->unique();
            $table->string('status', 25)->default('issued');
            $table->timestamp('issued_at');
            $table->timestamp('boarded_at')->nullable();
            $table->timestamps();
            $table->index(['boarding_token', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payments');
    }
};
