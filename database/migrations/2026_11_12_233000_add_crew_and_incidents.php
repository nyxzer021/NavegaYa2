<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document_number', 20);
            $table->string('phone', 30)->nullable();
            $table->string('role', 20);
            $table->string('license_number', 40)->nullable();
            $table->date('license_expires_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'document_number']);
        });

        Schema::create('departure_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_departure_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('air_departure_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('duty', 20);
            $table->timestamps();
        });

        Schema::create('departure_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_departure_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('air_departure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('severity', 20);
            $table->text('description');
            $table->string('action', 20);
            $table->timestamps();
        });

        Schema::create('incident_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departure_incident_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        $this->guard('crew_members', 'crew_members_check', "role in ('captain', 'engineer', 'sailor', 'pilot', 'copilot') and status in ('active', 'inactive')", <<<'SQL'
            new.role not in ('captain', 'engineer', 'sailor', 'pilot', 'copilot')
            or new.status not in ('active', 'inactive')
        SQL);
        $this->guard('departure_assignments', 'departure_assignments_check', "((route_departure_id is not null and air_departure_id is null) or (route_departure_id is null and air_departure_id is not null)) and duty in ('command', 'member')", <<<'SQL'
            (new.route_departure_id is null and new.air_departure_id is null)
            or (new.route_departure_id is not null and new.air_departure_id is not null)
            or new.duty not in ('command', 'member')
        SQL);
        $this->guard('departure_incidents', 'departure_incidents_check', "((route_departure_id is not null and air_departure_id is null) or (route_departure_id is null and air_departure_id is not null)) and kind in ('delay', 'breakdown', 'weather', 'river_level', 'other') and severity in ('low', 'medium', 'high') and action in ('record', 'reschedule', 'cancel')", <<<'SQL'
            (new.route_departure_id is null and new.air_departure_id is null)
            or (new.route_departure_id is not null and new.air_departure_id is not null)
            or new.kind not in ('delay', 'breakdown', 'weather', 'river_level', 'other')
            or new.severity not in ('low', 'medium', 'high')
            or new.action not in ('record', 'reschedule', 'cancel')
        SQL);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement('alter table crew_members drop constraint if exists crew_members_check');
            DB::statement('alter table departure_assignments drop constraint if exists departure_assignments_check');
            DB::statement('alter table departure_incidents drop constraint if exists departure_incidents_check');
        }
        if ($driver === 'sqlite') {
            foreach (['crew_members_insert', 'crew_members_update', 'departure_assignments_insert', 'departure_assignments_update', 'departure_incidents_insert', 'departure_incidents_update'] as $trigger) {
                DB::unprepared('drop trigger if exists '.$trigger);
            }
        }

        Schema::dropIfExists('incident_photos');
        Schema::dropIfExists('departure_incidents');
        Schema::dropIfExists('departure_assignments');
        Schema::dropIfExists('crew_members');
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
