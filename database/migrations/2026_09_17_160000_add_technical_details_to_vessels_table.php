<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->foreignId('base_port_id')->nullable()->after('organization_id')->constrained('ports')->nullOnDelete();
            $table->string('hull_material', 80)->nullable()->after('vessel_type');
            $table->decimal('gross_tonnage', 10, 2)->nullable()->after('crew_capacity');
            $table->decimal('length_m', 7, 2)->nullable()->after('gross_tonnage');
            $table->decimal('beam_m', 7, 2)->nullable()->after('length_m');
            $table->decimal('draft_m', 6, 2)->nullable()->after('beam_m');
            $table->string('engine_description', 180)->nullable()->after('draft_m');
            $table->unsignedSmallInteger('manufacture_year')->nullable()->after('engine_description');
            $table->string('insurance_policy', 120)->nullable()->after('manufacture_year');
            $table->date('insurance_expires_at')->nullable()->after('insurance_policy');
            $table->date('inspection_expires_at')->nullable()->after('insurance_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('base_port_id');
            $table->dropColumn(['hull_material', 'gross_tonnage', 'length_m', 'beam_m', 'draft_m', 'engine_description', 'manufacture_year', 'insurance_policy', 'insurance_expires_at', 'inspection_expires_at']);
        });
    }
};
