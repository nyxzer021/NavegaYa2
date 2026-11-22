<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->decimal('refund_regret_percent', 5, 2)->nullable()->after('cargo_threshold_kg');
        });

        Schema::create('ticket_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('reservation_id')->constrained();
            $table->foreignId('payment_id')->constrained();
            $table->string('source', 30);
            $table->string('cause', 30);
            $table->string('status', 20);
            $table->decimal('fare_amount', 10, 2);
            $table->decimal('fee_amount', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->decimal('policy_percent', 5, 2)->nullable();
            $table->string('explanation', 500);
            $table->string('reason', 500)->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->string('operation_number', 80)->nullable();
            $table->foreignId('web_fare_refund_id')->nullable()->constrained('web_fare_refunds')->nullOnDelete();
            $table->foreignId('cash_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_refunds');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('refund_regret_percent');
        });
    }
};
