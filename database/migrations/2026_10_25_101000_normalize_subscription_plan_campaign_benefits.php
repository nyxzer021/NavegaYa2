<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $placements = [
            'basic' => ['home_carousel'],
            'professional' => ['home_carousel', 'home_sidebar', 'home_recommendations'],
            'enterprise' => ['home_carousel', 'home_sidebar', 'home_recommendations'],
        ];

        DB::table('subscription_plans')->orderBy('id')->get()->each(function ($plan) use ($placements): void {
            $benefits = json_decode($plan->benefits ?: '[]', true) ?: [];
            $benefits['ad_limit'] = (int) ($benefits['ad_limit'] ?? 0);
            $benefits['campaign_days'] = (int) ($benefits['campaign_days'] ?? 30);
            $benefits['allowed_placements'] = $benefits['allowed_placements'] ?? ($placements[$plan->code] ?? ['home_carousel']);
            unset($benefits['placements']);
            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'benefits' => json_encode($benefits),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void {}
};
