<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departure_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('air_route_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('aircraft_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('weekdays');
            $table->string('departure_time', 5);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('boarding_offset_minutes')->nullable();
            $table->decimal('fare', 10, 2);
            $table->boolean('cargo_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::table('route_departures', function (Blueprint $table) {
            $table->foreignId('departure_series_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sales_paused_at')->nullable();
            $table->text('sales_pause_reason')->nullable();
            $table->foreignId('sales_paused_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['departure_series_id', 'departure_at'], 'route_departure_series_unique');
        });

        Schema::table('air_departures', function (Blueprint $table) {
            $table->foreignId('departure_series_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('previous_departure_at')->nullable();
            $table->string('disruption_type', 30)->nullable();
            $table->string('disruption_reason', 500)->nullable();
            $table->foreignId('status_changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('sales_paused_at')->nullable();
            $table->text('sales_pause_reason')->nullable();
            $table->foreignId('sales_paused_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['departure_series_id', 'departure_at'], 'air_departure_series_unique');
        });

        Schema::create('departure_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_departure_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('air_departure_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->text('reason');
            $table->string('recipient')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->text('marketplace_pause_reason')->nullable();
        });

        $this->guardSeries();
        $this->guardNotices();
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table departure_series drop constraint if exists departure_series_check');
            DB::statement('alter table departure_notices drop constraint if exists departure_notices_check');
        }
        if ($driver === 'sqlite') {
            foreach (['departure_series_insert', 'departure_series_update', 'departure_notices_insert', 'departure_notices_update'] as $trigger) {
                DB::unprepared('drop trigger if exists '.$trigger);
            }
        }

        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('marketplace_pause_reason'));
        Schema::dropIfExists('departure_notices');

        Schema::table('air_departures', function (Blueprint $table) {
            $table->dropUnique('air_departure_series_unique');
            $table->dropConstrainedForeignId('departure_series_id');
            $table->dropConstrainedForeignId('status_changed_by_user_id');
            $table->dropConstrainedForeignId('sales_paused_by_user_id');
            $table->dropColumn(['previous_departure_at', 'disruption_type', 'disruption_reason', 'status_changed_at', 'sales_paused_at', 'sales_pause_reason']);
        });
        Schema::table('route_departures', function (Blueprint $table) {
            $table->dropUnique('route_departure_series_unique');
            $table->dropConstrainedForeignId('departure_series_id');
            $table->dropConstrainedForeignId('sales_paused_by_user_id');
            $table->dropColumn(['sales_paused_at', 'sales_pause_reason']);
        });
        Schema::dropIfExists('departure_series');
    }

    private function guardSeries(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $parents = '(transport_route_id is not null and air_route_id is null and vessel_id is not null and aircraft_id is null) or (transport_route_id is null and air_route_id is not null and vessel_id is null and aircraft_id is not null)';
        $span = match ($driver) {
            'pgsql' => 'ends_on <= starts_on + 90',
            'mysql', 'mariadb' => 'ends_on <= date_add(starts_on, interval 90 day)',
            default => 'julianday(ends_on) - julianday(starts_on) <= 90',
        };
        $check = 'ends_on >= starts_on and '.$span.' and ('.$parents.')';

        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table departure_series add constraint departure_series_check check ('.$check.')');

            return;
        }
        if ($driver !== 'sqlite') {
            return;
        }

        $when = <<<'SQL'
            new.ends_on < new.starts_on
            or julianday(new.ends_on) - julianday(new.starts_on) > 90
            or (new.transport_route_id is null and new.air_route_id is null)
            or (new.transport_route_id is not null and new.air_route_id is not null)
            or (new.transport_route_id is not null and (new.vessel_id is null or new.aircraft_id is not null))
            or (new.air_route_id is not null and (new.aircraft_id is null or new.vessel_id is not null))
        SQL;
        foreach (['insert', 'update'] as $event) {
            DB::unprepared('create trigger departure_series_'.$event.' before '.$event.' on departure_series when '.$when.' begin select raise(abort, \'invalid departure series\'); end');
        }
    }

    private function guardNotices(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $check = '(route_departure_id is not null and air_departure_id is null) or (route_departure_id is null and air_departure_id is not null)';
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table departure_notices add constraint departure_notices_check check ('.$check.')');

            return;
        }
        if ($driver !== 'sqlite') {
            return;
        }
        $when = '(new.route_departure_id is null and new.air_departure_id is null) or (new.route_departure_id is not null and new.air_departure_id is not null)';
        foreach (['insert', 'update'] as $event) {
            DB::unprepared('create trigger departure_notices_'.$event.' before '.$event.' on departure_notices when '.$when.' begin select raise(abort, \'invalid departure notice\'); end');
        }
    }
};
