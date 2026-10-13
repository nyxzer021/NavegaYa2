<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $publisher = require database_path('migrations/2026_10_13_120000_publish_legal_and_security_policies.php');
        $publisher->up();
    }

    public function down(): void
    {
        // Las políticas legales deben permanecer publicadas si se revierte este ajuste visual.
    }
};
