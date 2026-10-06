<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->string('modality', 20)->default('fluvial')->after('name');
        });

        DB::table('ports')->whereRaw('LOWER(name) LIKE ?', ['%iquitos%'])->update(['city' => 'Iquitos']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->dropColumn('modality');
        });
    }
};
