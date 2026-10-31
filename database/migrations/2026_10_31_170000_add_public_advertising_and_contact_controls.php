<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('advertisements', 'display_order')) {
            Schema::table('advertisements', fn (Blueprint $table) => $table->unsignedSmallInteger('display_order')->default(100)->after('status'));
        }
        if (! Schema::hasColumn('advertisements', 'alt_text')) {
            Schema::table('advertisements', fn (Blueprint $table) => $table->string('alt_text', 180)->nullable()->after('banner_image_path'));
        }
        if (! Schema::hasColumn('organizations', 'whatsapp_message')) {
            Schema::table('organizations', fn (Blueprint $table) => $table->string('whatsapp_message', 500)->nullable()->after('whatsapp'));
        }
        if (! Schema::hasColumn('organizations', 'service_hours')) {
            Schema::table('organizations', fn (Blueprint $table) => $table->string('service_hours', 180)->nullable()->after('whatsapp_message'));
        }
        if (! Schema::hasColumn('organizations', 'show_public_whatsapp')) {
            Schema::table('organizations', fn (Blueprint $table) => $table->boolean('show_public_whatsapp')->default(true)->after('service_hours'));
        }
    }

    public function down(): void
    {
        foreach (['display_order', 'alt_text'] as $column) {
            if (Schema::hasColumn('advertisements', $column)) {
                Schema::table('advertisements', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        foreach (['whatsapp_message', 'service_hours', 'show_public_whatsapp'] as $column) {
            if (Schema::hasColumn('organizations', $column)) {
                Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
