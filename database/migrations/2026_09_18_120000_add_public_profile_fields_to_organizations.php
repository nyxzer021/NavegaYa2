<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('public_description')->nullable();
            $table->string('logo_path', 500)->nullable();
            $table->string('cover_image_path', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['public_description', 'logo_path', 'cover_image_path']));
    }
};
