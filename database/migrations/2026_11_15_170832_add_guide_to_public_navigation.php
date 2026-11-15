<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega la Guía Loreto al menú público y deja "Quiénes somos" en "Más".
     * Solo mueve "Quiénes somos" si conserva la posición original, para respetar cambios del superadmin.
     */
    public function up(): void
    {
        $now = now();

        if (! DB::table('navigation_items')->where('target_type', 'route')->where('target', 'guide.index')->exists()) {
            DB::table('navigation_items')->insert([
                'label' => 'Guía Loreto',
                'label_en' => 'Loreto guide',
                'target_type' => 'route',
                'target' => 'guide.index',
                'position' => 35,
                'is_active' => true,
                'opens_new_tab' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('navigation_items')
            ->where('target_type', 'route')->where('target', 'about')->where('position', 10)
            ->update(['position' => 90, 'updated_at' => $now]);
    }

    /**
     * Quita la Guía Loreto del menú y devuelve "Quiénes somos" a su posición original.
     */
    public function down(): void
    {
        DB::table('navigation_items')
            ->where('target_type', 'route')->where('target', 'guide.index')->where('position', 35)
            ->delete();

        DB::table('navigation_items')
            ->where('target_type', 'route')->where('target', 'about')->where('position', 90)
            ->update(['position' => 10]);
    }
};
