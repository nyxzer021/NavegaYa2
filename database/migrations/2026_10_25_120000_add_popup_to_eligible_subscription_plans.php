<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')->whereIn('code', ['professional', 'enterprise'])->get()->each(function ($plan): void {
            $benefits = json_decode($plan->benefits ?: '[]', true) ?: [];
            $placements = collect($benefits['allowed_placements'] ?? [])->push('home_popup')->unique()->values()->all();
            $benefits['allowed_placements'] = $placements;
            DB::table('subscription_plans')->where('id', $plan->id)->update(['benefits' => json_encode($benefits), 'updated_at' => now()]);
        });
    }

    public function down(): void
    {
        DB::table('subscription_plans')->get()->each(function ($plan): void {
            $benefits = json_decode($plan->benefits ?: '[]', true) ?: [];
            $benefits['allowed_placements'] = array_values(array_diff($benefits['allowed_placements'] ?? [], ['home_popup']));
            DB::table('subscription_plans')->where('id', $plan->id)->update(['benefits' => json_encode($benefits), 'updated_at' => now()]);
        });
    }
};
