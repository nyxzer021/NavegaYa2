<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_seats', function (Blueprint $table) {
            $table->string('space_type', 20)->default('seat');
            $table->string('cabin_code', 40)->nullable();
            $table->string('cabin_sold_as', 20)->nullable();
            $table->unsignedSmallInteger('bed_count')->nullable();
        });

        $this->guardPlaces();
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table vessel_seats drop constraint if exists vessel_seats_place_check');
        }

        if ($driver === 'sqlite') {
            DB::unprepared('drop trigger if exists vessel_seats_place_insert');
            DB::unprepared('drop trigger if exists vessel_seats_place_update');
        }

        Schema::table('vessel_seats', function (Blueprint $table) {
            $table->dropColumn(['space_type', 'cabin_code', 'cabin_sold_as', 'bed_count']);
        });
    }

    private function guardPlaces(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $check = <<<'SQL'
            space_type in ('seat', 'hammock', 'cabin_bed')
            and (
                (
                    space_type = 'cabin_bed'
                    and cabin_sold_as in ('whole', 'per_bed')
                    and bed_count is not null
                    and bed_count >= 1
                )
                or (
                    space_type in ('seat', 'hammock')
                    and cabin_sold_as is null
                    and bed_count is null
                    and cabin_code is null
                )
            )
        SQL;

        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table vessel_seats add constraint vessel_seats_place_check check ('.$check.')');

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $when = <<<'SQL'
            coalesce(new.space_type, 'seat') not in ('seat', 'hammock', 'cabin_bed')
            or (
                coalesce(new.space_type, 'seat') = 'cabin_bed'
                and (
                    new.cabin_sold_as is null
                    or new.cabin_sold_as not in ('whole', 'per_bed')
                    or new.bed_count is null
                    or new.bed_count < 1
                )
            )
            or (
                coalesce(new.space_type, 'seat') != 'cabin_bed'
                and (new.cabin_sold_as is not null or new.bed_count is not null or new.cabin_code is not null)
            )
        SQL;

        foreach (['insert', 'update'] as $event) {
            DB::unprepared("create trigger vessel_seats_place_{$event} before {$event} on vessel_seats for each row when {$when} begin select raise(abort, 'invalid vessel place'); end");
        }
    }
};
