<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_heroes', function (Blueprint $table) {
            $table->id();
            $table->string('page_key')->nullable()->unique();
            $table->foreignId('public_page_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('image_path')->nullable();
            $table->string('focal_point', 20)->default('center');
            $table->string('alt_text', 180)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_heroes');
    }
};
