<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_conversations', function (Blueprint $table): void {
            $table->foreignId('assigned_admin_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_admin_id');
        });
    }
};
