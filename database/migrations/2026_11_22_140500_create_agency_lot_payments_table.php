<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_lot_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('agency_lot_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->date('paid_on');
            $table->string('method', 40);
            $table->string('operation_number', 80);
            $table->string('note', 255)->nullable();
            $table->string('status', 20);
            $table->string('void_reason', 500)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'agency_lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_lot_payments');
    }
};
