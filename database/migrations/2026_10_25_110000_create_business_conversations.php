<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 140);
            $table->string('status', 20)->default('open');
            $table->timestamp('admin_last_read_at')->nullable();
            $table->timestamp('business_last_read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('business_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_messages');
        Schema::dropIfExists('business_conversations');
    }
};
