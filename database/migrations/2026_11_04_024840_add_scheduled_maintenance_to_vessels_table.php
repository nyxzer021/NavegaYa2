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
        Schema::table('vessels', function (Blueprint $table) {
            $table->date('maintenance_scheduled_on')->nullable()->after('inspection_expires_at');
            $table->string('maintenance_kind', 20)->nullable()->after('maintenance_scheduled_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->dropColumn(['maintenance_scheduled_on', 'maintenance_kind']);
        });
    }
};
