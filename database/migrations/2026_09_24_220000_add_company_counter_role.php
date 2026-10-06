<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['code' => 'company_counter'],
            ['name' => 'Counter / despachador', 'description' => 'Atiende embarque, pasajeros y carga únicamente para su empresa.', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('code', 'company_counter')->whereNotIn('id', fn ($query) => $query->select('role_id')->from('role_user'))->delete();
    }
};
