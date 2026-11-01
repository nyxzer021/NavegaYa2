<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keep Descubrir on Loreto unless an administrator turns another city on.
     */
    public function up(): void
    {
        DB::table('destination_cities')
            ->whereNotNull('department')
            ->where('department', '!=', 'Loreto')
            ->update(['show_in_discover' => false]);
    }

    /**
     * Restore the cities that were seeded outside Loreto.
     */
    public function down(): void
    {
        DB::table('destination_cities')
            ->whereIn('name', ['Pucallpa', 'Puerto Maldonado', 'Tarapoto'])
            ->update(['show_in_discover' => true]);
    }
};
