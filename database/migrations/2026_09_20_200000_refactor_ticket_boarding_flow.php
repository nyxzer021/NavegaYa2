<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('boarded_by_user_id')->nullable()->after('boarded_at')->constrained('users')->nullOnDelete();
            $table->foreignId('boarded_port_id')->nullable()->after('boarded_by_user_id')->constrained('ports')->nullOnDelete();
        });
        DB::table('tickets')->where('status', 'issued')->update(['status' => 'confirmed']);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boarded_port_id');
            $table->dropConstrainedForeignId('boarded_by_user_id');
        });
    }
};
