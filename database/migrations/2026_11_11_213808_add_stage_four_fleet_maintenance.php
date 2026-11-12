<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessel_maintenance_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('kind', 20);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['vessel_id', 'starts_on', 'ends_on']);
        });
        $this->guardDateRange('vessel_maintenance_periods');

        Schema::create('aircraft_maintenance_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aircraft_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('kind', 20);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['aircraft_id', 'starts_on', 'ends_on']);
        });
        $this->guardDateRange('aircraft_maintenance_periods');

        Schema::table('vessels', function (Blueprint $table) {
            $table->decimal('cargo_capacity_tons', 8, 2)->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('aircraft', function (Blueprint $table) {
            $table->string('aircraft_kind', 20)->nullable();
            $table->decimal('max_takeoff_weight_kg', 10, 2)->nullable();
            $table->decimal('useful_load_kg', 10, 2)->nullable();
            $table->decimal('max_baggage_kg_per_passenger', 6, 2)->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aircraft', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'aircraft_kind', 'max_takeoff_weight_kg', 'useful_load_kg', 'max_baggage_kg_per_passenger',
                'review_notes', 'submitted_for_review_at', 'reviewed_at', 'suspension_reason', 'suspended_at',
            ]);
        });

        Schema::table('vessels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['cargo_capacity_tons', 'suspension_reason', 'suspended_at']);
        });

        Schema::dropIfExists('aircraft_maintenance_periods');
        Schema::dropIfExists('vessel_maintenance_periods');
    }

    private function guardDateRange(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            DB::statement("alter table {$table} add constraint {$table}_ends_on_check check (ends_on >= starts_on)");

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        foreach (['insert', 'update'] as $event) {
            DB::unprepared("create trigger {$table}_ends_on_{$event} before {$event} on {$table} for each row when new.ends_on < new.starts_on begin select raise(abort, 'ends_on before starts_on'); end");
        }
    }
};
