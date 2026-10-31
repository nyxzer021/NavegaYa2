<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_heroes')) {
            return;
        }

        Schema::create('site_heroes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_heroes');
    }
};
