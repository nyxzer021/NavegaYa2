<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->decimal('focal_x', 5, 2)->nullable();
            $table->decimal('focal_y', 5, 2)->nullable();
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('organization_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('organization_page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label', 80);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('policy_documents', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 40);
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamps();
            $table->index(['kind', 'organization_id']);
        });

        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('summary')->nullable();
            $table->longText('body_html');
            $table->date('effective_on')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['policy_document_id', 'version']);
        });

        Schema::table('organization_photos', function (Blueprint $table) {
            $table->foreignId('album_id')->nullable()->after('organization_id')->constrained('organization_albums')->nullOnDelete();
            $table->foreignId('company_review_id')->nullable()->after('album_id')->constrained('company_reviews')->nullOnDelete();
            $table->string('status', 20)->default('published')->after('caption');
            $table->string('alt_text')->nullable()->after('status');
        });

        Schema::table('company_reviews', function (Blueprint $table) {
            $table->text('company_response')->nullable();
            $table->timestamp('company_responded_at')->nullable();
            $table->text('moderation_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
        });

        Schema::create('photo_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['organization_photo_id', 'user_id']);
        });

        Schema::create('photo_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('photo_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('user_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->text('reason');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('public_primary_color', 7)->nullable();
            $table->string('public_accent_color', 7)->nullable();
            $table->unsignedSmallInteger('baggage_hand_kg')->nullable();
            $table->unsignedSmallInteger('baggage_hold_kg')->nullable();
            $table->unsignedSmallInteger('baggage_air_kg')->nullable();
            $table->unsignedSmallInteger('cargo_threshold_kg')->nullable();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('travel_policy_version_id')->nullable()->constrained('policy_versions')->nullOnDelete();
            $table->foreignId('platform_terms_version_id')->nullable()->constrained('policy_versions')->nullOnDelete();
        });

        $now = now();
        foreach ([
            'baggage_hand_kg' => '10',
            'baggage_hold_kg' => '20',
            'baggage_air_hand_kg' => '10',
        ] as $key => $value) {
            if (! DB::table('system_settings')->where('key', $key)->exists()) {
                DB::table('system_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $page = DB::table('public_pages')->where('slug', 'terminos')->first();
        $body = $page?->content
            ? '<p>'.nl2br(e($page->content)).'</p>'
            : '<p>Términos y condiciones de NavegaYA.</p>';
        $documentId = DB::table('policy_documents')->insertGetId([
            'kind' => 'platform_terms',
            'organization_id' => null,
            'title' => $page->title ?? 'Términos y condiciones',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('policy_versions')->insert([
            'policy_document_id' => $documentId,
            'version' => 1,
            'summary' => $page->summary ?? 'Versión inicial tomada de la página pública.',
            'body_html' => $body,
            'effective_on' => $now->toDateString(),
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('travel_policy_version_id');
            $table->dropConstrainedForeignId('platform_terms_version_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'public_primary_color',
                'public_accent_color',
                'baggage_hand_kg',
                'baggage_hold_kg',
                'baggage_air_kg',
                'cargo_threshold_kg',
            ]);
        });

        Schema::dropIfExists('user_sanctions');
        Schema::dropIfExists('photo_comments');
        Schema::dropIfExists('photo_likes');

        Schema::table('company_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['company_response', 'company_responded_at', 'moderation_reason', 'moderated_at']);
        });

        Schema::table('organization_photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('album_id');
            $table->dropConstrainedForeignId('company_review_id');
            $table->dropColumn(['status', 'alt_text']);
        });

        Schema::dropIfExists('policy_versions');
        Schema::dropIfExists('policy_documents');
        Schema::dropIfExists('organization_page_sections');
        Schema::dropIfExists('organization_albums');
        Schema::dropIfExists('organization_banners');

        DB::table('system_settings')->whereIn('key', [
            'baggage_hand_kg',
            'baggage_hold_kg',
            'baggage_air_hand_kg',
        ])->delete();
    }
};
