<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('destination_city_id')->constrained()->cascadeOnDelete();
            $t->string('business_name');
            $t->string('category', 40);
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('target_url', 500)->nullable();
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->string('status', 20)->default('pending');
            $t->unsignedBigInteger('views')->default(0);
            $t->unsignedBigInteger('clicks')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
