<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->json('benefits_snapshot')->nullable()->after('currency'));
        DB::table('subscriptions')->orderBy('id')->each(function ($subscription): void {
            $benefits = DB::table('subscription_plans')->where('id', $subscription->subscription_plan_id)->value('benefits');
            DB::table('subscriptions')->where('id', $subscription->id)->update(['benefits_snapshot' => $benefits]);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('benefits_snapshot'));
    }
};
