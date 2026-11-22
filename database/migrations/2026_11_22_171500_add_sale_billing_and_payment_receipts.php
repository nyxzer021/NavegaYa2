<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->boolean('needs_invoice')->default(false);
            $table->string('buyer_document_type', 20)->nullable();
            $table->string('buyer_document_number', 20)->nullable();
            $table->string('buyer_legal_name', 160)->nullable();
            $table->string('buyer_address', 200)->nullable();
        });

        Schema::create('receipt_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->unsignedSmallInteger('series_year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'series_year']);
        });

        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('payment_id')->unique()->constrained();
            $table->unsignedSmallInteger('series_year');
            $table->unsignedInteger('sequence');
            $table->string('number', 20);
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['organization_id', 'series_year', 'sequence']);
            $table->unique(['organization_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('receipt_sequences');
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn([
                'needs_invoice', 'buyer_document_type', 'buyer_document_number', 'buyer_legal_name', 'buyer_address',
            ]);
        });
    }
};
