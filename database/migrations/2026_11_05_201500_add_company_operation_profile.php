<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_modalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('status', 20)->default('requested');
            $table->string('permit_scope', 20)->nullable();
            $table->string('permit_number', 80)->nullable();
            $table->date('permit_expires_on')->nullable();
            $table->string('permit_path')->nullable();
            $table->string('permit_original_name')->nullable();
            $table->string('aoc_number', 80)->nullable();
            $table->date('aoc_expires_on')->nullable();
            $table->string('aoc_path')->nullable();
            $table->string('aoc_original_name')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('organization_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('address');
            $table->string('city');
            $table->string('hours')->nullable();
            $table->boolean('receives_cargo')->default(false);
            $table->boolean('show_on_where_to_buy')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('hidden_by_admin')->default(false);
            $table->text('hidden_reason')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('base_port_id')->nullable()->after('base_city')->constrained('ports')->nullOnDelete();
            $table->string('representative_document_number', 20)->nullable()->after('contact_name');
            $table->string('representative_email')->nullable()->after('representative_document_number');
            $table->string('representative_phone', 30)->nullable()->after('representative_email');
            $table->string('whatsapp_cargo', 20)->nullable()->after('whatsapp_message');
            $table->string('whatsapp_hours_from', 5)->nullable()->after('whatsapp_cargo');
            $table->string('whatsapp_hours_until', 5)->nullable()->after('whatsapp_hours_from');
            $table->boolean('whatsapp_show_outside_hours')->default(false)->after('whatsapp_hours_until');
            $table->boolean('whatsapp_hidden_by_admin')->default(false)->after('show_public_whatsapp');
            $table->text('whatsapp_hidden_reason')->nullable()->after('whatsapp_hidden_by_admin');
            $table->foreignId('whatsapp_hidden_by')->nullable()->after('whatsapp_hidden_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('whatsapp_hidden_at')->nullable()->after('whatsapp_hidden_by');
        });

        Schema::table('organization_documents', function (Blueprint $table) {
            $table->date('expires_on')->nullable()->after('original_name');
            $table->string('reference_number', 80)->nullable()->after('expires_on');
        });

        $now = now();
        DB::table('organizations')
            ->where('type', 'transport_company')
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'modality'])
            ->each(function (object $organization) use ($now): void {
                $codes = match ($organization->modality) {
                    'aereo' => ['aereo'],
                    'mixto' => ['fluvial', 'aereo'],
                    default => ['fluvial'],
                };
                foreach ($codes as $code) {
                    DB::table('organization_modalities')->insert([
                        'organization_id' => $organization->id,
                        'code' => $code,
                        'status' => 'approved',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

        DB::table('advertisements')->where('target_url', 'like', '%51900000000%')->update(['target_url' => null]);
        if (Schema::hasColumn('advertisements', 'phone_whatsapp')) {
            DB::table('advertisements')
                ->whereIn('phone_whatsapp', ['+51 900 000 000', '51900000000', '900000000', '900 000 000'])
                ->update(['phone_whatsapp' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('organization_documents', function (Blueprint $table) {
            $table->dropColumn(['expires_on', 'reference_number']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_hidden_by');
            $table->dropConstrainedForeignId('base_port_id');
            $table->dropColumn([
                'representative_document_number', 'representative_email', 'representative_phone',
                'whatsapp_cargo', 'whatsapp_hours_from', 'whatsapp_hours_until', 'whatsapp_show_outside_hours',
                'whatsapp_hidden_by_admin', 'whatsapp_hidden_reason', 'whatsapp_hidden_at',
            ]);
        });

        Schema::dropIfExists('organization_sites');
        Schema::dropIfExists('organization_modalities');

        DB::table('advertisements')
            ->whereIn('business_name', ['Lodge Río Marañón', 'Sabores del Itaya', 'Amazonía Viva Tours'])
            ->whereNull('target_url')
            ->update(['target_url' => 'https://wa.me/51900000000', 'phone_whatsapp' => '+51 900 000 000']);
    }
};
