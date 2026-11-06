<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_sites', function (Blueprint $table) {
            $table->foreignId('port_id')->nullable()->after('kind')->constrained('ports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organization_sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('port_id');
        });
    }
};
