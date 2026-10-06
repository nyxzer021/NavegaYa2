<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('commission_rate', 5, 2)->default(0)->after('currency_code');
            $table->string('commission_status', 24)->default('pending')->after('commission_amount')->index();
            $table->timestamp('commission_invoiced_at')->nullable()->after('commission_status');
            $table->timestamp('commission_paid_at')->nullable()->after('commission_invoiced_at');
            $table->string('commission_reference', 120)->nullable()->after('commission_paid_at');
        });

        DB::table('payments')
            ->where('amount', '>', 0)
            ->where('commission_amount', '>', 0)
            ->update([
                'commission_rate' => DB::raw('ROUND((commission_amount / amount) * 100, 2)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['commission_status']);
            $table->dropColumn([
                'commission_rate',
                'commission_status',
                'commission_invoiced_at',
                'commission_paid_at',
                'commission_reference',
            ]);
        });
    }
};
