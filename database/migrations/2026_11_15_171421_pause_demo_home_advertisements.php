<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Anuncios de muestra creados en octubre sin un negocio real detrás.
     *
     * @var list<string>
     */
    private array $demoBusinesses = ['Lodge Río Marañón', 'Sabores del Itaya', 'Amazonía Viva Tours'];

    /**
     * Pausa los anuncios de muestra sin borrarlos: la web pública solo muestra negocios reales.
     */
    public function up(): void
    {
        DB::table('advertisements')
            ->whereNull('organization_id')
            ->whereIn('business_name', $this->demoBusinesses)
            ->where('status', 'active')
            ->update(['status' => 'paused', 'updated_at' => now()]);
    }

    /**
     * Vuelve a activar los anuncios de muestra.
     */
    public function down(): void
    {
        DB::table('advertisements')
            ->whereNull('organization_id')
            ->whereIn('business_name', $this->demoBusinesses)
            ->where('status', 'paused')
            ->update(['status' => 'active', 'updated_at' => now()]);
    }
};
