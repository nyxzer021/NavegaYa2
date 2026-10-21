<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_image_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_listing_image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 700);
            $table->string('status', 20)->default('published');
            $table->timestamps();
            $table->unique(['destination_listing_image_id', 'user_id']);
            $table->index(['destination_listing_image_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_image_reviews');
    }
};
