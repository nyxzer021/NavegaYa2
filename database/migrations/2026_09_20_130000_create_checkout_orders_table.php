<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('contact_name', 150);
            $table->string('contact_email', 150);
            $table->string('contact_phone', 40);
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('platform_fee_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('platform_fee_percent', 5, 2);
            $table->string('status', 25)->default('pending_payment');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('checkout_order_id')->nullable()->after('user_id')->constrained('checkout_orders')->nullOnDelete();
            $table->index(['checkout_order_id', 'status']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('checkout_order_id')->nullable()->after('reservation_id')->constrained('checkout_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('checkout_order_id'));
        Schema::table('reservations', fn (Blueprint $table) => $table->dropConstrainedForeignId('checkout_order_id'));
        Schema::dropIfExists('checkout_orders');
    }
};
