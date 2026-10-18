<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('listing_type', 40)->index();
            $table->string('name', 100);
            $table->string('icon', 12)->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(99);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('business_categories')->insert([
            ['code'=>'hotel','listing_type'=>'lodging','name'=>'Hotel','icon'=>'🏨','description'=>'Hoteles urbanos y de tránsito.','sort_order'=>10,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'lodge','listing_type'=>'lodging','name'=>'Lodge amazónico','icon'=>'🛖','description'=>'Lodges de naturaleza y alojamientos rurales.','sort_order'=>20,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'restaurant','listing_type'=>'gastronomy','name'=>'Restaurante','icon'=>'🍽️','description'=>'Restaurantes y propuestas gastronómicas.','sort_order'=>30,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'tour','listing_type'=>'attraction','name'=>'Tour y experiencia','icon'=>'🌿','description'=>'Excursiones, guías y experiencias turísticas.','sort_order'=>40,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'river_transport','listing_type'=>'tourist_transport','name'=>'Transporte turístico fluvial','icon'=>'🚤','description'=>'Traslados y recorridos turísticos por río.','sort_order'=>50,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'ground_transport','listing_type'=>'tourist_transport','name'=>'Transporte turístico terrestre','icon'=>'🚐','description'=>'Traslados turísticos terrestres.','sort_order'=>60,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'travel_service','listing_type'=>'traveler_service','name'=>'Servicio para viajeros','icon'=>'🧭','description'=>'Servicios complementarios para el viajero.','sort_order'=>70,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['code'=>'local_business','listing_type'=>'local_business','name'=>'Comercio local','icon'=>'🛍️','description'=>'Comercios, artesanía y productos regionales.','sort_order'=>80,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now],
        ]);

        Schema::table('destination_listings', function (Blueprint $table): void {
            $table->foreignId('business_category_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });

        foreach (DB::table('destination_listings')->get(['id','type','category']) as $listing) {
            $categoryId = DB::table('business_categories')->where('listing_type', $listing->type)->orderBy('sort_order')->value('id');
            if ($categoryId) DB::table('destination_listings')->where('id', $listing->id)->update(['business_category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('destination_listings', fn (Blueprint $table) => $table->dropConstrainedForeignId('business_category_id'));
        Schema::dropIfExists('business_categories');
    }
};
