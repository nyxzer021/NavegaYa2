<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('button_label')->default('Buscar salida');
            $table->string('button_url')->default('#buscar');
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $now = now();
        DB::table('home_hero_slides')->insert([
            ['image_path' => '/images/navegaya-hero-amazonas.png', 'title' => 'Tu viaje empieza en el río.', 'subtitle' => 'Busca rutas, compara empresas verificadas y reserva tu asiento para conocer la Amazonía a tu ritmo.', 'button_label' => 'Buscar salida', 'button_url' => '#buscar', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['image_path' => '/images/navegaya-hero-rio.png', 'title' => 'La Amazonía se navega mejor.', 'subtitle' => 'Encuentra salidas verificadas y organiza tu viaje con información clara.', 'button_label' => 'Explorar rutas', 'button_url' => '/rutas-fluviales', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['image_path' => '/images/navegaya-hero-atardecer.png', 'title' => 'Destinos que nacen junto al agua.', 'subtitle' => 'Viaja por rutas fluviales y descubre la riqueza de nuestra Amazonía.', 'button_label' => 'Explorar destinos', 'button_url' => '#destinos', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('home_hero_slides');
    }
};
