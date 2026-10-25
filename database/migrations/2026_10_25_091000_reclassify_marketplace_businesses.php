<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organizations')
            ->whereIn('id', DB::table('destination_listings')->select('organization_id'))
            ->update(['type' => 'marketplace_business']);
    }

    public function down(): void
    {
        // La clasificación anterior no puede reconstruirse de forma segura.
    }
};
