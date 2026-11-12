<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_routes', function (Blueprint $table) {
            $table->foreignId('requested_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_route_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('air_route_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('port_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('minutes_from_origin');
            $table->timestamps();
            $table->unique(['transport_route_id', 'position'], 'route_stops_river_position_unique');
            $table->unique(['air_route_id', 'position'], 'route_stops_air_position_unique');
            $table->unique(['transport_route_id', 'port_id'], 'route_stops_river_port_unique');
            $table->unique(['air_route_id', 'port_id'], 'route_stops_air_port_unique');
        });

        $this->guardStops();
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table route_stops drop constraint if exists route_stops_check');
        }

        if ($driver === 'sqlite') {
            DB::unprepared('drop trigger if exists route_stops_insert');
            DB::unprepared('drop trigger if exists route_stops_update');
        }

        Schema::dropIfExists('route_stops');

        Schema::table('master_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_organization_id');
            $table->dropColumn('rejection_reason');
        });
    }

    private function guardStops(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $check = <<<'SQL'
            minutes_from_origin >= 1
            and position >= 1
            and (
                (transport_route_id is not null and air_route_id is null)
                or (transport_route_id is null and air_route_id is not null)
            )
        SQL;

        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table route_stops add constraint route_stops_check check ('.$check.')');

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $when = <<<'SQL'
            new.minutes_from_origin is null
            or new.minutes_from_origin < 1
            or new.position is null
            or new.position < 1
            or (new.transport_route_id is null and new.air_route_id is null)
            or (new.transport_route_id is not null and new.air_route_id is not null)
        SQL;

        foreach (['insert', 'update'] as $event) {
            DB::unprepared('create trigger route_stops_'.$event.' before '.$event.' on route_stops when '.$when.' begin select raise(abort, \'invalid route stop\'); end');
        }
    }
};
