<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('group_name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'permission_id']);
        });
        DB::table('permissions')->insert(array_map(fn ($permission) => array_merge($permission, ['created_at' => now(), 'updated_at' => now()]), [
            ['code' => 'companies', 'name' => 'Gobernanza y Aprobación de Empresas', 'group_name' => 'Gobernanza', 'description' => 'Revisar solicitudes, validar empresas y administrar su habilitación.'],
            ['code' => 'ports', 'name' => 'Puertos de embarque', 'group_name' => 'Operación', 'description' => 'Registrar y editar puertos.'],
            ['code' => 'routes', 'name' => 'Gestión de Catálogo y Tramos Maestros', 'group_name' => 'Catálogo regional', 'description' => 'Administrar puertos, conexiones oficiales y plantillas de tramos maestros.'],
            ['code' => 'fleet', 'name' => 'Embarcaciones y planos', 'group_name' => 'Operación', 'description' => 'Administrar flota y planos de asientos.'],
            ['code' => 'cargo', 'name' => 'Carga', 'group_name' => 'Ventas', 'description' => 'Registrar, verificar y entregar carga.'],
            ['code' => 'boarding', 'name' => 'Control de embarque', 'group_name' => 'Ventas', 'description' => 'Validar boletos y registrar embarques.'],
            ['code' => 'reports', 'name' => 'Finanzas, Liquidaciones y Comisiones', 'group_name' => 'Finanzas', 'description' => 'Consultar GMV, liquidaciones a empresas y comisiones de la plataforma.'],
            ['code' => 'ads', 'name' => 'Directorio Turístico Guía Loreto', 'group_name' => 'Marketing', 'description' => 'Gestionar negocios turísticos, anuncios y contenido de Guía Loreto.'],
            ['code' => 'audit', 'name' => 'Seguridad, Auditoría y Configuración', 'group_name' => 'Sistema', 'description' => 'Administrar seguridad, trazabilidad y configuración general.'],
            ['code' => 'users', 'name' => 'Usuarios y permisos', 'group_name' => 'Sistema', 'description' => 'Crear administradores y configurar accesos.'],
        ]));
        DB::table('roles')->updateOrInsert(['code' => 'admin'], ['name' => 'Administrador con permisos', 'description' => 'Accede únicamente a los módulos asignados.', 'updated_at' => now(), 'created_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permissions');
        DB::table('roles')->where('code', 'admin')->delete();
    }
};
