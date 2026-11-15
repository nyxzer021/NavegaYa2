<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PAGE = 'Tu página en la Guía Loreto con fotos, horarios y WhatsApp';

    private const ALL_PLACEMENTS = 'Todas las ubicaciones: portada, resultados de búsqueda y después de la compra';

    private const STATS = 'Estadísticas de visitas y contactos';

    /**
     * Textos de venta originales, que prometían funciones que el sistema no tiene (A/B testing, Account Manager, impresiones garantizadas).
     *
     * @var array<string, array{name: string, features: list<string>}>
     */
    private array $original = [
        'basic' => ['name' => 'Básico', 'features' => ['1 anuncio simultáneo', '5,000 impresiones/mes', '1 placement (homepage)', 'Analytics básico']],
        'professional' => ['name' => 'Profesional', 'features' => ['3 anuncios simultáneos', '15,000 impresiones/mes', '3 placements', 'Analytics avanzado + A/B testing']],
        'enterprise' => ['name' => 'Enterprise', 'features' => ['Anuncios ilimitados', '50,000 impresiones/mes', 'Todos los placements', 'Account Manager 24/7']],
    ];

    /**
     * Reemplaza los textos solo si siguen siendo los originales, para respetar lo que el superadmin ya haya editado.
     */
    public function up(): void
    {
        $honest = [
            'basic' => ['name' => 'Básico', 'features' => [self::PAGE, '1 campaña publicitaria a la vez', 'Ubicación: carrusel de la portada', self::STATS]],
            'professional' => ['name' => 'Profesional', 'features' => [self::PAGE, '3 campañas publicitarias a la vez', self::ALL_PLACEMENTS, self::STATS]],
            'enterprise' => ['name' => 'Empresarial', 'features' => [self::PAGE, 'Campañas publicitarias sin límite', self::ALL_PLACEMENTS, self::STATS]],
        ];

        $this->swap($this->original, $honest);
    }

    /**
     * Devuelve los textos originales.
     */
    public function down(): void
    {
        $honest = [
            'basic' => ['name' => 'Básico', 'features' => [self::PAGE, '1 campaña publicitaria a la vez', 'Ubicación: carrusel de la portada', self::STATS]],
            'professional' => ['name' => 'Profesional', 'features' => [self::PAGE, '3 campañas publicitarias a la vez', self::ALL_PLACEMENTS, self::STATS]],
            'enterprise' => ['name' => 'Empresarial', 'features' => [self::PAGE, 'Campañas publicitarias sin límite', self::ALL_PLACEMENTS, self::STATS]],
        ];

        $this->swap($honest, $this->original);
    }

    /**
     * @param  array<string, array{name: string, features: list<string>}>  $from
     * @param  array<string, array{name: string, features: list<string>}>  $to
     */
    private function swap(array $from, array $to): void
    {
        foreach ($to as $code => $target) {
            $plan = DB::table('subscription_plans')->where('code', $code)->first();
            if (! $plan) {
                continue;
            }

            $benefits = json_decode((string) $plan->benefits, true) ?: [];
            if (($benefits['features'] ?? null) !== $from[$code]['features']) {
                continue;
            }

            $benefits['features'] = $target['features'];
            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'name' => $plan->name === $from[$code]['name'] ? $target['name'] : $plan->name,
                'benefits' => json_encode($benefits, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }
};
