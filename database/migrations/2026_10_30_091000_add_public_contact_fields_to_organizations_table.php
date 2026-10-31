<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('whatsapp_message', 500)->nullable()->after('whatsapp');
            $table->string('service_hours', 180)->nullable()->after('phone');
            $table->boolean('show_whatsapp')->default(true)->after('whatsapp_message');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_message', 'service_hours', 'show_whatsapp']);
        });
    }
};
