<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table): void {
            $table->text('review_notes')->nullable()->after('status');
            $table->timestamp('submitted_for_review_at')->nullable()->after('review_notes');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_for_review_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_notes', 'submitted_for_review_at', 'reviewed_at']);
        });
    }
};
