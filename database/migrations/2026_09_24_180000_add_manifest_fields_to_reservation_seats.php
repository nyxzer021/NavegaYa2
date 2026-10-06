<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->string('document_type', 20)->default('DNI')->after('passenger_name');
            $table->unsignedTinyInteger('passenger_age')->nullable()->after('document_number');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_seats', fn (Blueprint $table) => $table->dropColumn(['document_type', 'passenger_age']));
    }
};
