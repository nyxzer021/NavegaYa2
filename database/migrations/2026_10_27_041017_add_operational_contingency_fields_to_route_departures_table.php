<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('route_departures', function (Blueprint $table) {
            $table->timestamp('previous_departure_at')->nullable()->after('departure_at');
            $table->string('disruption_type', 30)->nullable()->after('status');
            $table->string('disruption_reason', 500)->nullable()->after('disruption_type');
            $table->foreignId('status_changed_by_user_id')->nullable()->after('disruption_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable()->after('status_changed_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_departures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_changed_by_user_id');
            $table->dropColumn(['previous_departure_at', 'disruption_type', 'disruption_reason', 'status_changed_at']);
        });
    }
};
