<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('route_departure_id')->constrained()->restrictOnDelete();
            $t->string('code', 30)->unique();
            $t->string('sender_name');
            $t->string('sender_document', 40)->nullable();
            $t->string('sender_phone', 30);
            $t->string('recipient_name');
            $t->string('recipient_document', 40)->nullable();
            $t->string('recipient_phone', 30)->nullable();
            $t->text('description');
            $t->unsignedSmallInteger('package_count');
            $t->decimal('declared_weight_kg', 10, 2);
            $t->decimal('verified_weight_kg', 10, 2)->nullable();
            $t->decimal('rate_per_kg', 10, 2)->nullable();
            $t->decimal('amount', 10, 2)->nullable();
            $t->string('payment_status', 25)->default('pending_terminal');
            $t->string('status', 25)->default('registered');
            $t->string('receipt_number', 80)->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->index(['route_departure_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_shipments');
    }
};
