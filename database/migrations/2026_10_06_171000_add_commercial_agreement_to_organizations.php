<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('commercial_plan', 32)->default('standard')->after('commission_rate');
            $table->string('agreement_number', 80)->nullable()->after('commercial_plan');
            $table->date('commission_starts_on')->nullable()->after('agreement_number');
            $table->date('commission_ends_on')->nullable()->after('commission_starts_on');
            $table->text('commission_notes')->nullable()->after('commission_ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['commercial_plan', 'agreement_number', 'commission_starts_on', 'commission_ends_on', 'commission_notes']);
        });
    }
};
