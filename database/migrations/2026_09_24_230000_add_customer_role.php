<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['code' => 'customer'],
            [
                'name' => 'Cliente / pasajero',
                'description' => 'Compra pasajes y consulta únicamente sus propios boletos y códigos QR.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('code', 'customer')
            ->whereNotIn('id', fn ($query) => $query->select('role_id')->from('role_user'))
            ->delete();
    }
};
