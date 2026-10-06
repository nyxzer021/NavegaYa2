<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table): void {
            $table->string('dicapi_certificate_number', 120)->nullable()->after('insurance_policy');
            $table->unsignedSmallInteger('life_vest_count')->nullable()->after('dicapi_certificate_number');
            $table->text('emergency_equipment')->nullable()->after('life_vest_count');
        });
        Schema::table('aircraft', function (Blueprint $table): void {
            $table->string('manufacturer', 120)->nullable()->after('model');
            $table->string('dgac_certificate_number', 120)->nullable()->after('manufacturer');
            $table->string('airworthiness_certificate_number', 120)->nullable()->after('dgac_certificate_number');
            $table->date('airworthiness_expires_at')->nullable()->after('airworthiness_certificate_number');
            $table->string('aviation_policy', 120)->nullable()->after('airworthiness_expires_at');
            $table->date('aviation_policy_expires_at')->nullable()->after('aviation_policy');
            $table->text('emergency_equipment')->nullable()->after('aviation_policy_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('vessels', fn (Blueprint $table) => $table->dropColumn(['dicapi_certificate_number', 'life_vest_count', 'emergency_equipment']));
        Schema::table('aircraft', fn (Blueprint $table) => $table->dropColumn(['manufacturer', 'dgac_certificate_number', 'airworthiness_certificate_number', 'airworthiness_expires_at', 'aviation_policy', 'aviation_policy_expires_at', 'emergency_equipment']));
    }
};
