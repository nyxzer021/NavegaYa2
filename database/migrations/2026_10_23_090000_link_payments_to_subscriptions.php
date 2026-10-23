<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('reservation_id')->nullable()->change();
            $table->foreignId('subscription_id')->nullable()->after('checkout_order_id')->constrained()->nullOnDelete();
            $table->string('purpose', 30)->default('ticket')->after('subscription_id');
            $table->boolean('requires_review')->default(false)->after('status');
            $table->string('review_reason', 500)->nullable()->after('requires_review');
            $table->index(['purpose', 'requires_review', 'status']);
        });
    }

    public function down(): void
    {
        DB::table('payments')->where('purpose', 'subscription')->delete();

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['purpose', 'requires_review', 'status']);
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn(['purpose', 'requires_review', 'review_reason']);
            $table->foreignId('reservation_id')->nullable(false)->change();
        });
    }
};
