<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_fares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('air_route_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('space_type', 20);
            $table->string('passenger_type', 20);
            $table->decimal('amount', 10, 2);
            $table->boolean('includes_meal')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['transport_route_id', 'space_type', 'passenger_type'], 'route_fares_river_lookup');
            $table->index(['air_route_id', 'space_type', 'passenger_type'], 'route_fares_air_lookup');
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20);
            $table->decimal('value', 10, 2);
            $table->json('channels');
            $table->boolean('requires_document')->default(false);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('discount_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('air_route_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('discount_held')->default(false);
        });

        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->string('passenger_type', 20)->default('adult');
        });

        $this->guardFares();
        $this->guardDiscounts();
        $this->guardDiscountRoutes();
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table route_fares drop constraint if exists route_fares_check');
            DB::statement('alter table discounts drop constraint if exists discounts_check');
            DB::statement('alter table discount_routes drop constraint if exists discount_routes_check');
        }
        if ($driver === 'sqlite') {
            foreach (['route_fares_insert', 'route_fares_update', 'discounts_insert', 'discounts_update', 'discount_routes_insert', 'discount_routes_update'] as $trigger) {
                DB::unprepared('drop trigger if exists '.$trigger);
            }
        }

        Schema::table('reservation_seats', fn (Blueprint $table) => $table->dropColumn('passenger_type'));
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_id');
            $table->dropColumn('discount_held');
        });
        Schema::dropIfExists('discount_routes');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('route_fares');
    }

    private function guardFares(): void
    {
        $spaces = "space_type in ('seat', 'hammock', 'cabin_bed')";
        $passengers = "passenger_type in ('adult', 'child', 'student', 'senior', 'infant')";
        $oneRoute = '(transport_route_id is not null and air_route_id is null) or (transport_route_id is null and air_route_id is not null)';
        $airSeat = '(air_route_id is null or space_type = \'seat\')';
        $span = '(ends_on is null or starts_on is null or ends_on >= starts_on)';
        $check = $oneRoute.' and '.$spaces.' and '.$passengers.' and amount >= 0 and '.$airSeat.' and '.$span;
        $when = <<<'SQL'
            (new.transport_route_id is null and new.air_route_id is null)
            or (new.transport_route_id is not null and new.air_route_id is not null)
            or new.space_type not in ('seat', 'hammock', 'cabin_bed')
            or new.passenger_type not in ('adult', 'child', 'student', 'senior', 'infant')
            or new.amount < 0
            or (new.air_route_id is not null and new.space_type != 'seat')
            or (new.ends_on is not null and new.starts_on is not null and new.ends_on < new.starts_on)
        SQL;
        $this->guard('route_fares', 'route_fares_check', $check, $when);
    }

    private function guardDiscounts(): void
    {
        $check = "kind in ('percent', 'amount') and value >= 0 and (kind <> 'percent' or value <= 100) and redeemed_count >= 0 and (usage_limit is null or usage_limit >= 0) and (ends_on is null or starts_on is null or ends_on >= starts_on)";
        $when = <<<'SQL'
            new.kind not in ('percent', 'amount')
            or new.value < 0
            or (new.kind = 'percent' and new.value > 100)
            or new.redeemed_count < 0
            or (new.usage_limit is not null and new.usage_limit < 0)
            or (new.ends_on is not null and new.starts_on is not null and new.ends_on < new.starts_on)
        SQL;
        $this->guard('discounts', 'discounts_check', $check, $when);
    }

    private function guardDiscountRoutes(): void
    {
        $check = '(transport_route_id is not null and air_route_id is null) or (transport_route_id is null and air_route_id is not null)';
        $when = '(new.transport_route_id is null and new.air_route_id is null) or (new.transport_route_id is not null and new.air_route_id is not null)';
        $this->guard('discount_routes', 'discount_routes_check', $check, $when);
    }

    private function guard(string $table, string $constraint, string $check, string $when): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table '.$table.' add constraint '.$constraint.' check ('.$check.')');

            return;
        }
        if ($driver !== 'sqlite') {
            return;
        }
        foreach (['insert', 'update'] as $event) {
            DB::unprepared('create trigger '.$table.'_'.$event.' before '.$event.' on '.$table.' when '.$when.' begin select raise(abort, \'invalid '.$table.'\'); end');
        }
    }
};
