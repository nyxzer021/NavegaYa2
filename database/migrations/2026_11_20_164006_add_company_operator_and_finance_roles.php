<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('roles')->updateOrInsert(
            ['code' => 'company_operator'],
            ['name' => 'Operador', 'description' => 'Programa salidas, embarca y cuida la flota. No ve dinero.', 'created_at' => $now, 'updated_at' => $now]
        );
        DB::table('roles')->updateOrInsert(
            ['code' => 'company_finance'],
            ['name' => 'Finanzas', 'description' => 'Revisa ventas, cobros y cajas. No abre, cierra ni anula.', 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('roles')
            ->whereIn('code', ['company_operator', 'company_finance'])
            ->whereNotIn('id', fn ($query) => $query->select('role_id')->from('role_user'))
            ->delete();
    }
};
