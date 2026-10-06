<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aircraft_seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('aircraft_id')->constrained()->cascadeOnDelete();
            $t->string('cabin')->default('Económica');
            $t->string('code', 12);
            $t->unsignedSmallInteger('row_position');
            $t->unsignedSmallInteger('column_position');
            $t->boolean('is_available')->default(true);
            $t->timestamps();
            $t->unique(['aircraft_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aircraft_seats');
    }
};
