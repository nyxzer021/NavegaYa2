<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->string('port_type', 40)->default('embarcadero')->after('name');
            $table->string('operator_name', 120)->nullable()->after('port_type');
            $table->string('contact_name', 120)->nullable()->after('operator_name');
            $table->string('contact_phone', 40)->nullable()->after('contact_name');
            $table->string('operating_hours', 120)->nullable()->after('contact_phone');
            $table->text('access_notes')->nullable()->after('address');
            $table->text('available_services')->nullable()->after('access_notes');
        });
    }

    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->dropColumn(['port_type', 'operator_name', 'contact_name', 'contact_phone', 'operating_hours', 'access_notes', 'available_services']);
        });
    }
};
