<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('content_type', 40);
            $table->unsignedBigInteger('content_id');
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->string('resolution', 30)->nullable();
            $table->text('moderator_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index(['content_type', 'content_id']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('profile_content_status', 20)->default('published')->after('cover_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('profile_content_status'));
        Schema::dropIfExists('content_reports');
    }
};
