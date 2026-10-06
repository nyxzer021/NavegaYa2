<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('label_en')->nullable();
            $table->enum('target_type', ['route', 'page', 'url']);
            $table->string('target');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('opens_new_tab')->default(false);
            $table->timestamps();
        });
        $now = now();
        DB::table('navigation_items')->insert([
            ['label' => 'Quiénes somos', 'label_en' => 'About us', 'target_type' => 'route', 'target' => 'about', 'position' => 10, 'is_active' => 1, 'opens_new_tab' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Rutas fluviales', 'label_en' => 'River routes', 'target_type' => 'route', 'target' => 'routes.index', 'position' => 20, 'is_active' => 1, 'opens_new_tab' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Empresas', 'label_en' => 'Companies', 'target_type' => 'route', 'target' => 'companies.index', 'position' => 30, 'is_active' => 1, 'opens_new_tab' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Descubrir', 'label_en' => 'Discover', 'target_type' => 'route', 'target' => 'discover.index', 'position' => 40, 'is_active' => 1, 'opens_new_tab' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Busca tu pasaje', 'label_en' => 'Find my ticket', 'target_type' => 'route', 'target' => 'travels.index', 'position' => 50, 'is_active' => 1, 'opens_new_tab' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
        Schema::dropIfExists('public_pages');
    }
};
