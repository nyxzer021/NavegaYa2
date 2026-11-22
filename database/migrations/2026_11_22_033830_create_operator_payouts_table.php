<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 10, 2);
            $table->string('method', 40);
            $table->string('operation_number', 80);
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('paid');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'paid_on']);
        });

        Schema::create('operator_payout_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operator_payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            $table->unique(['operator_payout_id', 'payment_id']);
        });

        Schema::create('web_fare_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('reason', 500);
            $table->timestamp('refunded_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_fare_refunds');
        Schema::dropIfExists('operator_payout_items');
        Schema::dropIfExists('operator_payouts');
    }
};
