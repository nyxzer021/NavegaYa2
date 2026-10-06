<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('registration_number')->unique();
            $table->string('vessel_type');
            $table->unsignedSmallInteger('passenger_capacity');
            $table->unsignedSmallInteger('crew_capacity')->default(0);
            $table->string('status')->default('draft');
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessels');
    }
};
