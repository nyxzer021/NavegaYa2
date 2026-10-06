<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(8)->after('status');
            $table->string('bank_name', 120)->nullable()->after('commission_rate');
            $table->string('bank_account', 40)->nullable()->after('bank_name');
            $table->string('bank_cci', 40)->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'bank_name', 'bank_account', 'bank_cci']);
        });
    }
};
