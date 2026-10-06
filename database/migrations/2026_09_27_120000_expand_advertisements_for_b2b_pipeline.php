<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->unsignedBigInteger('destination_city_id')->nullable()->change();
            $table->string('business_type', 40)->nullable()->after('business_name');
            $table->string('contact_name')->nullable()->after('business_type');
            $table->string('phone_whatsapp', 30)->nullable()->after('contact_name');
            $table->string('email')->nullable()->after('phone_whatsapp');
            $table->string('city_destination')->default('Iquitos')->after('email');
            $table->string('ruc', 11)->nullable()->after('city_destination');
            $table->json('placements')->nullable()->after('ruc');
            $table->string('banner_image_path', 500)->nullable()->after('image_url');
            $table->decimal('monthly_fee', 10, 2)->default(300)->after('ends_on');
            $table->text('admin_notes')->nullable()->after('status');
        });

        DB::table('advertisements')->where('status', 'pending')->update(['status' => 'lead_pending']);
        DB::table('advertisements')->whereNull('placements')->update(['placements' => json_encode(['home_hero'])]);
    }

    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->unsignedBigInteger('destination_city_id')->nullable(false)->change();
            $table->dropColumn(['business_type', 'contact_name', 'phone_whatsapp', 'email', 'city_destination', 'ruc', 'placements', 'banner_image_path', 'monthly_fee', 'admin_notes']);
        });
    }
};
