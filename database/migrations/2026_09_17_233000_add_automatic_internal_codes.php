<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', fn (Blueprint $t) => $t->string('internal_code', 30)->nullable()->unique()->after('id'));
        Schema::table('ports', fn (Blueprint $t) => $t->string('internal_code', 30)->nullable()->unique()->after('id'));
        Schema::table('vessels', fn (Blueprint $t) => $t->string('internal_code', 30)->nullable()->unique()->after('id'));
        Schema::table('route_departures', fn (Blueprint $t) => $t->string('code', 30)->nullable()->unique()->after('id'));
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $t) => $t->dropUnique(['internal_code'])->dropColumn('internal_code'));
        Schema::table('ports', fn (Blueprint $t) => $t->dropUnique(['internal_code'])->dropColumn('internal_code'));
        Schema::table('vessels', fn (Blueprint $t) => $t->dropUnique(['internal_code'])->dropColumn('internal_code'));
        Schema::table('route_departures', fn (Blueprint $t) => $t->dropUnique(['code'])->dropColumn('code'));
    }
};
