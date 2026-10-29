<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $contextual = ['search_results', 'trip_complete', 'post_purchase'];

    public function up(): void
    {
        $eligiblePlanIds = DB::table('subscription_plans')->whereIn('code', ['professional', 'enterprise'])->pluck('id');
        DB::table('subscription_plans')->whereIn('id', $eligiblePlanIds)->get()->each(function ($plan): void {
            $benefits = json_decode($plan->benefits ?: '[]', true) ?: [];
            $benefits['allowed_placements'] = collect($benefits['allowed_placements'] ?? [])->merge($this->contextual)->unique()->values()->all();
            DB::table('subscription_plans')->where('id', $plan->id)->update(['benefits' => json_encode($benefits), 'updated_at' => now()]);
        });
        DB::table('subscriptions')->whereIn('subscription_plan_id', $eligiblePlanIds)->whereIn('status', ['pending', 'active', 'overdue'])->get()->each(function ($subscription): void {
            $benefits = json_decode($subscription->benefits_snapshot ?: '[]', true) ?: [];
            $benefits['allowed_placements'] = collect($benefits['allowed_placements'] ?? [])->merge($this->contextual)->unique()->values()->all();
            DB::table('subscriptions')->where('id', $subscription->id)->update(['benefits_snapshot' => json_encode($benefits), 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        DB::table('subscription_plans')->get()->each(function ($plan): void {
            $benefits = json_decode($plan->benefits ?: '[]', true) ?: [];
            $benefits['allowed_placements'] = array_values(array_diff($benefits['allowed_placements'] ?? [], $this->contextual));
            DB::table('subscription_plans')->where('id', $plan->id)->update(['benefits' => json_encode($benefits), 'updated_at' => now()]);
        });
        DB::table('subscriptions')->get()->each(function ($subscription): void {
            $benefits = json_decode($subscription->benefits_snapshot ?: '[]', true) ?: [];
            $benefits['allowed_placements'] = array_values(array_diff($benefits['allowed_placements'] ?? [], $this->contextual));
            DB::table('subscriptions')->where('id', $subscription->id)->update(['benefits_snapshot' => json_encode($benefits), 'updated_at' => now()]);
        });
    }
};
