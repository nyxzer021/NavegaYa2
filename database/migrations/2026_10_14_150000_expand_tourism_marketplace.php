<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('destination_listings', function (Blueprint $table): void { $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete(); $table->foreignId('owner_user_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete(); $table->string('status',30)->default('published')->after('type'); $table->string('banner_path',500)->nullable()->after('opening_hours'); $table->text('services')->nullable()->after('description'); $table->text('moderation_notes')->nullable()->after('is_active'); $table->index(['status','type']); }); DB::table('destination_listings')->update(['status'=>'published']); DB::table('roles')->updateOrInsert(['code'=>'marketplace_admin'],['name'=>'Administrador de negocio turístico','description'=>'Gestiona la ficha, galería y publicidad de un negocio del marketplace.','updated_at'=>now(),'created_at'=>now()]); }
 public function down(): void { Schema::table('destination_listings', function (Blueprint $table): void { $table->dropIndex(['status','type']); $table->dropConstrainedForeignId('organization_id'); $table->dropConstrainedForeignId('owner_user_id'); $table->dropColumn(['status','banner_path','services','moderation_notes']); }); }
};
