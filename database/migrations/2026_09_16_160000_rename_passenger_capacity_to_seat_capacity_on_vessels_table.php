<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vessels RENAME COLUMN passenger_capacity TO seat_capacity');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vessels RENAME COLUMN seat_capacity TO passenger_capacity');
    }
};
