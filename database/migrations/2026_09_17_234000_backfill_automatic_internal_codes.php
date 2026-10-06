<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['organizations' => 'EMP', 'ports' => 'PTO', 'vessels' => 'NAV'] as $table => $prefix) {
            DB::table($table)->whereNull('internal_code')->orderBy('id')->get(['id'])->each(fn ($row) => DB::table($table)->where('id', $row->id)->update(['internal_code' => $prefix.'-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]));
        } DB::table('route_departures')->whereNull('code')->orderBy('id')->get(['id', 'departure_at'])->each(fn ($row) => DB::table('route_departures')->where('id', $row->id)->update(['code' => 'SAL-'.date('ymd', strtotime($row->departure_at)).'-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT)]));
    }

    public function down(): void {}
};
