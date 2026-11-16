<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_guide_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('lima_on');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'lima_on']);
        });

        Schema::create('cargo_shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cargo_shipment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('happened_at');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::table('cargo_shipments', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('length_cm')->nullable()->after('declared_weight_kg');
            $table->unsignedSmallInteger('width_cm')->nullable()->after('length_cm');
            $table->unsignedSmallInteger('height_cm')->nullable()->after('width_cm');
            $table->boolean('fragile')->default(false)->after('height_cm');
            $table->string('payment_method', 20)->nullable()->after('amount');
            $table->foreignId('collected_by_user_id')->nullable()->after('payment_method')->constrained('users')->nullOnDelete();
            $table->foreignId('organization_site_id')->nullable()->after('collected_by_user_id')->constrained()->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $table->foreignId('boarded_by_user_id')->nullable()->after('received_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('boarded_at')->nullable()->after('boarded_by_user_id');
            $table->foreignId('delivered_by_user_id')->nullable()->after('delivered_at')->constrained('users')->nullOnDelete();
            $table->string('collector_name')->nullable()->after('delivered_by_user_id');
            $table->string('collector_document', 40)->nullable()->after('collector_name');
            $table->boolean('needs_reassignment')->default(false)->after('collector_document');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('cargo_shipment_id')->nullable()->after('reservation_id')->constrained()->nullOnDelete();
            $table->foreignId('collected_by_user_id')->nullable()->after('cargo_shipment_id')->constrained('users')->nullOnDelete();
            $table->foreignId('organization_site_id')->nullable()->after('collected_by_user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_site_id');
            $table->dropConstrainedForeignId('collected_by_user_id');
            $table->dropConstrainedForeignId('cargo_shipment_id');
        });

        Schema::table('cargo_shipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivered_by_user_id');
            $table->dropConstrainedForeignId('boarded_by_user_id');
            $table->dropConstrainedForeignId('received_by_user_id');
            $table->dropConstrainedForeignId('organization_site_id');
            $table->dropConstrainedForeignId('collected_by_user_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn([
                'length_cm', 'width_cm', 'height_cm', 'fragile', 'payment_method',
                'boarded_at', 'collector_name', 'collector_document', 'needs_reassignment',
            ]);
        });

        Schema::dropIfExists('cargo_shipment_events');
        Schema::dropIfExists('cargo_guide_counters');
    }
};
