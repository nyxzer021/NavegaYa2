<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargo_shipments', function (Blueprint $table) {
            $table->foreignId('received_at_site_id')->nullable()->after('organization_site_id')->constrained('organization_sites')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('authorized_by_user_id')->nullable()->after('collected_by_user_id')->constrained('users')->nullOnDelete();
        });

        DB::table('cargo_shipments')
            ->whereNull('received_at_site_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row): void {
                $siteId = $row->organization_site_id;
                if (! $siteId) {
                    $payment = DB::table('payments')
                        ->where('cargo_shipment_id', $row->id)
                        ->whereNotNull('organization_site_id')
                        ->orderBy('id')
                        ->first(['organization_site_id', 'cash_session_id']);
                    $siteId = $payment?->organization_site_id;
                    if (! $siteId && $payment?->cash_session_id) {
                        $siteId = DB::table('cash_sessions')->where('id', $payment->cash_session_id)->value('organization_site_id');
                    }
                }
                if (! $siteId && $row->received_by_user_id && $row->received_at) {
                    $siteId = DB::table('cash_sessions')
                        ->where('organization_id', $row->organization_id)
                        ->where('user_id', $row->received_by_user_id)
                        ->where('opened_at', '<=', $row->received_at)
                        ->where(function ($query) use ($row): void {
                            $query->whereNull('closed_at')->orWhere('closed_at', '>=', $row->received_at);
                        })
                        ->orderByDesc('opened_at')
                        ->value('organization_site_id');
                }
                if ($siteId) {
                    DB::table('cargo_shipments')->where('id', $row->id)->update(['received_at_site_id' => $siteId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('authorized_by_user_id');
        });

        Schema::table('cargo_shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_at_site_id');
        });
    }
};
