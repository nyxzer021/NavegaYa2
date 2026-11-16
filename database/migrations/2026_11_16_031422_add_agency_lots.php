<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ruc', 11);
            $table->string('contact_name');
            $table->string('phone', 30);
            $table->string('status', 20)->default('active');
            $table->decimal('max_surcharge_percent', 5, 2)->default(0);
            $table->string('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('agency_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_departure_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 10, 2);
            $table->string('payment_status', 20)->default('pending');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_lot_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_seat_id')->constrained()->cascadeOnDelete();
            $table->string('note');
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('agency_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 80)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('route_departure_seat_holds', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('kind')->constrained()->nullOnDelete();
            $table->foreignId('agency_lot_id')->nullable()->after('agency_id')->constrained()->nullOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('organization_site_id')->constrained()->nullOnDelete();
            $table->foreignId('agency_lot_id')->nullable()->after('agency_id')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agency_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('organization_site_id')->nullable()->after('agency_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_site_id');
            $table->dropConstrainedForeignId('agency_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_lot_id');
            $table->dropConstrainedForeignId('agency_id');
        });

        Schema::table('route_departure_seat_holds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_lot_id');
            $table->dropConstrainedForeignId('agency_id');
        });

        Schema::dropIfExists('agency_invitations');
        Schema::dropIfExists('agency_lot_releases');
        Schema::dropIfExists('agency_lots');
        Schema::dropIfExists('agencies');
    }
};
