<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('route_name', 160)->nullable()->after('user_agent');
            $table->string('http_method', 10)->nullable()->after('route_name');
            $table->index(['route_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['route_name', 'created_at']);
            $table->dropColumn(['route_name', 'http_method']);
        });
    }
};
