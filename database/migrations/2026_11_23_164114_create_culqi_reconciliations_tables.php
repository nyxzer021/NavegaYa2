<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('culqi_settlement_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10);
            $table->string('file_hash', 64)->nullable()->unique();
            $table->string('filename', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamps();
        });

        Schema::create('culqi_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('culqi_settlement_imports')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('provider_reference', 80)->unique();
            $table->date('occurred_on');
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->nullable();
            $table->decimal('net', 10, 2)->nullable();
            $table->timestamps();
            $table->index('occurred_on');
        });

        Schema::create('reconciliation_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('difference_key', 120)->unique();
            $table->string('note', 500);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_reviews');
        Schema::dropIfExists('culqi_settlement_lines');
        Schema::dropIfExists('culqi_settlement_imports');
    }
};
