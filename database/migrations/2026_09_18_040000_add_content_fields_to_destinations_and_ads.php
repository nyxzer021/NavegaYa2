<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destination_cities', function (Blueprint $t) {
            $t->text('summary')->nullable();
            $t->text('attractions')->nullable();
            $t->text('lodging')->nullable();
            $t->text('gastronomy')->nullable();
            $t->string('image_url', 500)->nullable();
        });
        Schema::table('advertisements', function (Blueprint $t) {
            $t->string('image_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', fn (Blueprint $t) => $t->dropColumn('image_url'));
        Schema::table('destination_cities', fn (Blueprint $t) => $t->dropColumn(['summary', 'attractions', 'lodging', 'gastronomy', 'image_url']));
    }
};
