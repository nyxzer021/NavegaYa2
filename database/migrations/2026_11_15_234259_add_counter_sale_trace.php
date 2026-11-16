<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_departure_seat_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_departure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_seat_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('agency');
            $table->timestamps();
            $table->unique(['route_departure_id', 'vessel_seat_id'], 'departure_seat_hold_unique');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('sold_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('organization_site_id')->nullable()->after('sold_by_user_id')->constrained()->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('paid_at');
            $table->foreignId('voided_by_user_id')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by_user_id');
        });

        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->string('first_name', 80)->nullable()->after('passenger_name');
            $table->string('last_name', 80)->nullable()->after('first_name');
            $table->string('guardian_document', 40)->nullable()->after('document_number');
            $table->unsignedSmallInteger('hand_kg')->nullable()->after('passenger_type');
            $table->unsignedSmallInteger('hold_kg')->nullable()->after('hand_kg');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('cash_tendered', 10, 2)->nullable()->after('amount');
            $table->decimal('cash_change', 10, 2)->nullable()->after('cash_tendered');
            $table->string('operation_number', 40)->nullable()->after('cash_change');
            $table->timestamp('voided_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['cash_tendered', 'cash_change', 'operation_number', 'voided_at']);
        });

        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'guardian_document', 'hand_kg', 'hold_kg']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropConstrainedForeignId('organization_site_id');
            $table->dropConstrainedForeignId('sold_by_user_id');
            $table->dropColumn(['voided_at', 'void_reason']);
        });

        Schema::dropIfExists('route_departure_seat_holds');
    }
};
