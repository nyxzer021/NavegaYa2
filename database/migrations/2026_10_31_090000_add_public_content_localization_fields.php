<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_pages', function (Blueprint $table): void {
            $table->string('title_en')->nullable()->after('title');
            $table->text('summary_en')->nullable()->after('summary');
            $table->longText('content_en')->nullable()->after('content');
        });
        Schema::table('destination_cities', function (Blueprint $table): void {
            $table->string('image_alt')->nullable()->after('image_url');
            $table->string('image_alt_en')->nullable()->after('image_alt');
            $table->text('summary_en')->nullable()->after('summary');
            $table->text('attractions_en')->nullable()->after('attractions');
            $table->text('lodging_en')->nullable()->after('lodging');
            $table->text('gastronomy_en')->nullable()->after('gastronomy');
            $table->boolean('show_in_discover')->default(true)->after('is_active');
        });
        Schema::table('master_routes', function (Blueprint $table): void {
            $table->unsignedInteger('estimated_duration_minutes')->nullable()->after('estimated_duration_text');
            $table->boolean('duration_needs_review')->default(false)->after('estimated_duration_minutes');
        });

        DB::table('master_routes')->select('id', 'estimated_duration_text')->orderBy('id')->each(function ($route): void {
            $value = trim((string) $route->estimated_duration_text);
            if (preg_match('/^(?:(\d+) h)?(?: ?(\d+) min)?$/', $value, $parts) && ($parts[1] ?? $parts[2] ?? null) !== null) {
                DB::table('master_routes')->where('id', $route->id)->update([
                    'estimated_duration_minutes' => ((int) ($parts[1] ?? 0) * 60) + (int) ($parts[2] ?? 0),
                    'duration_needs_review' => false,
                ]);
            } elseif ($value !== '') {
                DB::table('master_routes')->where('id', $route->id)->update(['duration_needs_review' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('public_pages', fn (Blueprint $table) => $table->dropColumn(['title_en', 'summary_en', 'content_en']));
        Schema::table('destination_cities', fn (Blueprint $table) => $table->dropColumn(['image_alt', 'image_alt_en', 'summary_en', 'attractions_en', 'lodging_en', 'gastronomy_en', 'show_in_discover']));
        Schema::table('master_routes', fn (Blueprint $table) => $table->dropColumn(['estimated_duration_minutes', 'duration_needs_review']));
    }
};
