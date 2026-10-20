<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('destination_listings', function (Blueprint $table): void {
            $table->unsignedBigInteger('views')->default(0)->after('is_active');
            $table->unsignedBigInteger('contact_clicks')->default(0)->after('views');
        });

        Schema::create('destination_listing_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('destination_listing_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('offer_type', 60)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_unit', 60)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(99);
            $table->timestamps();
            $table->index(['destination_listing_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_listing_offers');
        Schema::table('destination_listings', fn (Blueprint $table) => $table->dropColumn(['views', 'contact_clicks']));
    }
};
