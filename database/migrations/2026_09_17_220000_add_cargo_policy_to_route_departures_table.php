<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_departures', function (Blueprint $table) {
            $table->timestamp('cargo_reception_starts_at')->nullable()->after('boarding_starts_at');
            $table->unsignedSmallInteger('included_baggage_kg')->default(25)->after('cargo_enabled');
            $table->string('cargo_payment_location', 30)->default('terminal')->after('included_baggage_kg');
            $table->text('cargo_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('route_departures', function (Blueprint $table) {
            $table->dropColumn(['cargo_reception_starts_at', 'included_baggage_kg', 'cargo_payment_location', 'cargo_notes']);
        });
    }
};
