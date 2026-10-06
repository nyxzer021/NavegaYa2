<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_seats', function (Blueprint $table) {
            $table->dropUnique('vessel_seats_vessel_id_code_unique');
            $table->unique(['vessel_id', 'deck', 'code'], 'vessel_seat_deck_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vessel_seats', function (Blueprint $table) {
            $table->dropUnique('vessel_seat_deck_code_unique');
            $table->unique(['vessel_id', 'code']);
        });
    }
};
