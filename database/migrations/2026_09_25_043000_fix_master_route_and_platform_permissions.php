<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $nautaPortId = DB::table('ports')
            ->where('city', 'Nauta')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN name = 'Puerto de Nauta' THEN 0 ELSE 1 END")
            ->value('id');

        if ($nautaPortId) {
            DB::table('master_routes')->where('code', 'TRM-F-3-1')->update([
                'destination_city' => 'Nauta',
                'destination_port_id' => $nautaPortId,
                'river_basin' => 'Río Huallaga / Marañón',
                'updated_at' => now(),
            ]);
        }

        $permissions = [
            'companies' => ['name' => 'Gobernanza y Aprobación de Empresas', 'group_name' => 'Gobernanza', 'description' => 'Revisar solicitudes, validar empresas y administrar su habilitación.'],
            'routes' => ['name' => 'Gestión de Catálogo y Tramos Maestros', 'group_name' => 'Catálogo regional', 'description' => 'Administrar puertos, conexiones oficiales y plantillas de tramos maestros.'],
            'reports' => ['name' => 'Finanzas, Liquidaciones y Comisiones', 'group_name' => 'Finanzas', 'description' => 'Consultar GMV, liquidaciones a empresas y comisiones de la plataforma.'],
            'ads' => ['name' => 'Directorio Turístico Guía Loreto', 'group_name' => 'Marketing', 'description' => 'Gestionar negocios turísticos, anuncios y contenido de Guía Loreto.'],
            'audit' => ['name' => 'Seguridad, Auditoría y Configuración', 'group_name' => 'Sistema', 'description' => 'Administrar seguridad, trazabilidad y configuración general.'],
        ];

        foreach ($permissions as $code => $attributes) {
            DB::table('permissions')->where('code', $code)->update([
                ...$attributes,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('code', 'companies')->update(['name' => 'Empresas y solicitudes', 'group_name' => 'Operación', 'description' => 'Revisar y administrar empresas y agencias.', 'updated_at' => now()]);
        DB::table('permissions')->where('code', 'routes')->update(['name' => 'Rutas y salidas', 'group_name' => 'Operación', 'description' => 'Crear rutas y programar salidas.', 'updated_at' => now()]);
        DB::table('permissions')->where('code', 'reports')->update(['name' => 'Reportes', 'group_name' => 'Ventas', 'description' => 'Consultar indicadores operativos.', 'updated_at' => now()]);
        DB::table('permissions')->where('code', 'ads')->update(['name' => 'Publicidad', 'group_name' => 'Contenido', 'description' => 'Gestionar campañas publicitarias.', 'updated_at' => now()]);
        DB::table('permissions')->where('code', 'audit')->update(['name' => 'Auditoría y seguridad', 'group_name' => 'Sistema', 'description' => 'Consultar el historial de actividad.', 'updated_at' => now()]);
    }
};
