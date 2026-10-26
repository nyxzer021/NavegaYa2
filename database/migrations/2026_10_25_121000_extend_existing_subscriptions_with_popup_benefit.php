<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $eligiblePlanIds = DB::table('subscription_plans')->whereIn('code', ['professional', 'enterprise'])->pluck('id');
        DB::table('subscriptions')->whereIn('subscription_plan_id', $eligiblePlanIds)
            ->whereIn('status', ['pending', 'active', 'overdue'])->get()->each(function ($subscription): void {
                $benefits = json_decode($subscription->benefits_snapshot ?: '[]', true) ?: [];
                $benefits['allowed_placements'] = collect($benefits['allowed_placements'] ?? [])->push('home_popup')->unique()->values()->all();
                DB::table('subscriptions')->where('id', $subscription->id)->update(['benefits_snapshot' => json_encode($benefits), 'updated_at' => now()]);
            });
    }

    public function down(): void
    {
        DB::table('subscriptions')->get()->each(function ($subscription): void {
            $benefits = json_decode($subscription->benefits_snapshot ?: '[]', true) ?: [];
            $benefits['allowed_placements'] = array_values(array_diff($benefits['allowed_placements'] ?? [], ['home_popup']));
            DB::table('subscriptions')->where('id', $subscription->id)->update(['benefits_snapshot' => json_encode($benefits), 'updated_at' => now()]);
        });
    }
};
