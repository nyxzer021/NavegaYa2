<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('short_code', 6)->nullable()->unique()->after('commercial_name');
        });

        $used = [];
        $rows = DB::table('organizations')->orderBy('id')->get(['id', 'legal_name', 'commercial_name']);
        foreach ($rows as $row) {
            $code = $this->sigla((string) ($row->commercial_name ?: $row->legal_name), $used);
            $used[] = $code;
            DB::table('organizations')->where('id', $row->id)->update(['short_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['short_code']);
            $table->dropColumn('short_code');
        });
    }

    /**
     * @param  array<int, string>  $used
     */
    private function sigla(string $name, array $used): string
    {
        $name = strtr($name, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $letters = preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($name))) ?? '';
        $base = substr(str_pad($letters !== '' ? $letters : 'EMP', 3, 'X'), 0, 5);
        $code = substr(str_pad($base, 3, 'X'), 0, 6);
        $suffix = 'A';
        while (in_array($code, $used, true)) {
            $code = substr($base, 0, 5).$suffix;
            $suffix = $suffix === 'Z' ? 'A' : chr(ord($suffix) + 1);
        }

        return $code;
    }
};
