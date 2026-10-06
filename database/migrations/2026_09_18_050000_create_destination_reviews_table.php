<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('destination_city_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('rating');
            $t->text('comment');
            $t->string('status', 20)->default('pending');
            $t->timestamps();
            $t->unique(['destination_city_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_reviews');
    }
};
