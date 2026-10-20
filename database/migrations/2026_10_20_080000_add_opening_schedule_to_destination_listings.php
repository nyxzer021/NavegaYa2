<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destination_listings', function (Blueprint $table) {
            $table->json('opening_schedule')->nullable()->after('opening_hours');
        });
    }

    public function down(): void
    {
        Schema::table('destination_listings', function (Blueprint $table) {
            $table->dropColumn('opening_schedule');
        });
    }
};
