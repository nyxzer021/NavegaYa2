<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreignId('scheduled_plan_id')->nullable()->after('subscription_plan_id')
                ->constrained('subscription_plans')->nullOnDelete();
            $table->date('scheduled_plan_starts_on')->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scheduled_plan_id');
            $table->dropColumn('scheduled_plan_starts_on');
        });
    }
};
