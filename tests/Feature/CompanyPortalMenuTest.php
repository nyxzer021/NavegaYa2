<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPortalMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_the_operator_menu_and_preparation_pages(): void
    {
        [$admin] = $this->companyAdmin();

        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Inicio',
                'Mi empresa', 'Datos de la empresa', 'Sedes y terminales', 'Pagos en línea',
                'Canales de venta', 'Punto de venta', 'Agencias autorizadas', 'Encomiendas', 'Envío de carga', 'Cierre de caja',
                'Ventas y finanzas', 'Historial de ventas', 'Cobros y conciliación', 'Reembolsos', 'Comisiones de agencias', 'Comprobantes', 'Reportes',
                'Operación', 'Flota', 'Rutas', 'Programación de salidas', 'Tarifas y descuentos', 'Embarque y manifiestos', 'Tripulación', 'Incidencias',
                'Usuarios y privilegios', 'Usuarios', 'Perfiles y permisos', 'Auditoría',
                'Contenido', 'Página de la empresa', 'Secciones de la página', 'Galería y opiniones', 'Políticas de viaje', 'Apariencia', 'Campañas publicitarias',
                'Ayuda', 'Mensajes con NavegaYA', 'Soporte', 'WhatsApp de atención',
            ])
            ->assertSee('Ventas web: por configurar')
            ->assertSee('Puerto base por definir')
            ->assertDontSee('Boletos vendidos')
            ->assertDontSee('Cargo de servicio NavegaYA');

        $this->actingAs($admin)->get(route('company.collections.index'))
            ->assertOk()
            ->assertSee('Cobros y conciliación')
            ->assertSee('no mostramos un conteo');

        $this->actingAs($admin)->get(route('company.refunds.index'))
            ->assertOk()
            ->assertSee('Reembolsos');

        $this->actingAs($admin)->get(route('company.online-payments.index'))
            ->assertOk()
            ->assertSee('Pagos en línea')
            ->assertSee('Mercado Pago');
    }

    public function test_counter_cannot_open_finance_preparation_pages(): void
    {
        [, $organization] = $this->companyAdmin();
        $counter = User::factory()->create();
        $counter->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Counter'])->id, ['organization_id' => $organization->id]);
        $counter->organizations()->attach($organization->id, ['status' => 'active']);

        $this->actingAs($counter)->get(route('company.collections.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.refunds.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.online-payments.index'))->assertForbidden();
        $this->actingAs($counter)->get(route('company.pos.index'))
            ->assertOk()
            ->assertSee('Punto de venta')
            ->assertSee('Cierre de caja')
            ->assertSee('Mis ventas')
            ->assertDontSee('Programar salida')
            ->assertDontSee('Cobros y conciliación')
            ->assertDontSee('Datos de la empresa')
            ->assertDontSee('Usuarios y privilegios')
            ->assertDontSee('Página de la empresa')
            ->assertDontSee('Flota');
        $this->actingAs($counter)->get(route('company.cash-close.index'))->assertOk()->assertSee('Cierre de caja');
    }

    public function test_super_admin_menu_follows_the_same_group_order(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador'])->id);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Inicio',
                'Bandeja de solicitudes',
                'Empresas',
                'Empresas y afiliaciones',
                'Agencias autorizadas',
                'Conexiones de pago',
                'Canales y ventas',
                'Ventas de pasajes',
                'Reportes',
                'Operación',
                'Flota por validar',
                'Supervisión de salidas',
                'Marketplace',
                'Negocios anunciantes',
                'Contenido y moderación',
                'Políticas base',
                'Sistema',
                'Auditoría',
                'Ayuda',
                'Mensajes con empresas',
            ]);

        $this->actingAs($admin)->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('Agencias autorizadas')
            ->assertSee('En preparación');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function companyAdmin(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Operador Menú',
            'commercial_name' => 'Menú Fast',
            'ruc' => '20999999001',
            'email' => 'menu@test.pe',
            'status' => 'active',
            'base_city' => 'Iquitos',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }
}
