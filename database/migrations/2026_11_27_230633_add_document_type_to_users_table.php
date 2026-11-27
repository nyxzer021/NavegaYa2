<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo de documento del viajero para llenar solo el formulario de compra.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('document_type', 20)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
