<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_city_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('price_reference', 80)->nullable();
            $table->string('opening_hours', 160)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(99);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'is_active', 'is_featured']);
        });
        Schema::create('destination_listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_listing_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_listing_images');
        Schema::dropIfExists('destination_listings');
    }
};
