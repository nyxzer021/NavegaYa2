<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_reviews', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'user_id']);
            $table->foreignId('reservation_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unique('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_reviews', function (Blueprint $table) {
            $table->dropUnique(['reservation_id']);
            $table->dropConstrainedForeignId('reservation_id');
            $table->unique(['organization_id', 'user_id']);
        });
    }
};
