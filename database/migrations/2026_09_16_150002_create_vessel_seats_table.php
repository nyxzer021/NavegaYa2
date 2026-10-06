<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessel_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('deck')->default('principal');
            $table->string('seat_class')->default('standard');
            $table->unsignedSmallInteger('row_position')->nullable();
            $table->unsignedSmallInteger('column_position')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['vessel_id', 'code']);
            $table->unique(['vessel_id', 'deck', 'row_position', 'column_position'], 'vessel_seat_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_seats');
    }
};
