<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', fn (Blueprint $t) => $t->string('contact_document', 40)->nullable()->after('contact_phone'));
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $t) => $t->dropColumn('contact_document'));
    }
};
