<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('document_number', 40)->nullable()->after('phone');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'document_number']);
        });
    }
};
