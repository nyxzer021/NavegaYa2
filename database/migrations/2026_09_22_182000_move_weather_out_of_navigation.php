<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('navigation_items')->where('target_type', 'route')->where('target', 'weather.index')->delete();
    }

    public function down(): void
    {
        DB::table('navigation_items')->insert(['label' => 'Clima en Loreto', 'label_en' => 'Loreto weather', 'target_type' => 'route', 'target' => 'weather.index', 'position' => 45, 'is_active' => true, 'opens_new_tab' => false, 'created_at' => now(), 'updated_at' => now()]);
    }
};
