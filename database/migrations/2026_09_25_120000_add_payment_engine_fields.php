<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->decimal('pending_payout_balance', 12, 2)->default(0)->after('commission_rate');
            $table->decimal('physical_sales_commission_rate', 5, 2)->default(0)->after('pending_payout_balance');
        });
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('sales_channel', 20)->default('web')->after('status');
            $table->string('payment_method', 30)->nullable()->after('sales_channel');
            $table->timestamp('paid_at')->nullable()->after('expires_at');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('PEN')->after('amount');
            $table->decimal('commission_amount', 10, 2)->default(0)->after('currency_code');
            $table->decimal('operator_net', 10, 2)->default(0)->after('commission_amount');
            $table->string('webhook_event_id', 120)->nullable()->unique()->after('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['currency_code', 'commission_amount', 'operator_net', 'webhook_event_id']));
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['sales_channel', 'payment_method', 'paid_at']));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['pending_payout_balance', 'physical_sales_commission_rate']));
    }
};
