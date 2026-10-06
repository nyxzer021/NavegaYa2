<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        DB::table('permissions')->updateOrInsert(['code' => 'settings'], ['name' => 'Configuración general', 'group_name' => 'Sistema', 'description' => 'Configura canales de atención y datos públicos.', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        DB::table('permissions')->where('code', 'settings')->delete();
    }
};
