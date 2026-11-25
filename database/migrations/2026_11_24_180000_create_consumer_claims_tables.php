<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_claim_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 40);
            $table->unsignedSmallInteger('series_year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['provider_key', 'series_year']);
        });

        Schema::create('consumer_claims', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 20);
            $table->string('provider_key', 40);
            $table->foreignId('organization_id')->nullable()->constrained();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reservation_code', 40)->nullable();
            $table->unsignedSmallInteger('series_year');
            $table->unsignedInteger('sequence');
            $table->string('code', 20);
            $table->string('public_token', 80)->unique();
            $table->date('filed_on');
            $table->date('due_on');
            $table->string('consumer_name', 160);
            $table->string('document_type', 20);
            $table->string('document_number', 20);
            $table->string('address', 200);
            $table->string('phone', 30);
            $table->string('email', 160);
            $table->boolean('is_minor')->default(false);
            $table->string('guardian_name', 160)->nullable();
            $table->string('guardian_document', 20)->nullable();
            $table->string('guardian_relationship', 20)->nullable();
            $table->string('good_kind', 20);
            $table->decimal('amount', 10, 2);
            $table->string('good_description', 500);
            $table->string('claim_type', 20);
            $table->text('detail');
            $table->text('request_text');
            $table->boolean('email_accepted');
            $table->text('response_text')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider_key', 'series_year', 'sequence']);
            $table->unique(['provider_key', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_claims');
        Schema::dropIfExists('consumer_claim_sequences');
    }
};
