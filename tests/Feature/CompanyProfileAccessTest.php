<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyPortalProfile;
use App\Models\Agency;
use App\Models\CashSession;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Support\CompanyPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CompanyProfileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_company_route_is_decided_for_every_profile(): void
    {
        $this->assertSame(EnsureCompanyPortalProfile::SELLER_ROUTES, CompanyPermissions::SELLER_ROUTES);
        $this->assertSame(EnsureCompanyPortalProfile::AGENCY_ROUTES, CompanyPermissions::AGENCY_ROUTES);

        $users = $this->profiles();
        $names = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! is_string($name) || ! str_starts_with($name, 'company.')) {
                continue;
            }
            $names[] = $name;
            foreach (['admin', 'seller', 'agency', 'operator', 'finance'] as $profile) {
                $this->assertNotNull(
                    CompanyPermissions::decision($profile, $name),
                    'Sin decidir: '.$profile.' '.$name,
                );
            }
            if (! CompanyPermissions::isPortalRoute($name)) {
                continue;
            }
            $uri = '/'.preg_replace('/\{([^}?]+)\??\}/', '999999', $route->uri());
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                foreach ($users as $profile => $user) {
                    $response = $this->actingAs($user)->call($method, $uri);
                    $label = $profile.' '.$method.' '.$name;
                    if (CompanyPermissions::decision($profile, $name) === true) {
                        $this->assertNotSame(403, $response->getStatusCode(), $label.' devolvió 403');
                    } else {
                        $this->assertSame(403, $response->getStatusCode(), $label.' devolvió '.$response->getStatusCode());
                    }
                }
            }
        }

        $this->assertNotEmpty($names);
    }

    public function test_each_profile_sees_only_its_menu(): void
    {
        $users = $this->profiles();

        $this->actingAs($users['operator'])->get(route('company.departures.index'))
            ->assertOk()
            ->assertSee('Programación de salidas')
            ->assertSee('Embarque y manifiestos')
            ->assertSee('Flota')
            ->assertSee('Tripulación')
            ->assertSee('Incidencias')
            ->assertDontSee('Punto de venta')
            ->assertDontSee('Historial de ventas')
            ->assertDontSee('Mis ventas')
            ->assertDontSee('Cierre de caja')
            ->assertDontSee('Cobros y conciliación');

        $this->actingAs($users['finance'])->get(route('company.sales.index'))
            ->assertOk()
            ->assertSee('Historial de ventas')
            ->assertSee('Cierre de caja')
            ->assertSee('Cobros y conciliación')
            ->assertSee('Reembolsos')
            ->assertSee('Comisiones de agencias')
            ->assertSee('Comprobantes')
            ->assertSee('Reportes')
            ->assertDontSee('Punto de venta')
            ->assertDontSee('Mis ventas')
            ->assertDontSee('Programación de salidas')
            ->assertDontSee('Flota');

        $this->actingAs($users['seller'])->get(route('company.pos.index'))
            ->assertOk()
            ->assertSee('Punto de venta')
            ->assertSee('Mis ventas')
            ->assertSee('Cierre de caja')
            ->assertDontSee('Programación de salidas');

        $this->actingAs($users['agency'])->get(route('company.pos.index'))
            ->assertOk()
            ->assertSee('Mis lotes')
            ->assertSee('Mis ventas')
            ->assertDontSee('Encomiendas y carga')
            ->assertDontSee('Cierre de caja');
    }

    public function test_finance_can_view_cash_and_cannot_open_close_or_void(): void
    {
        $users = $this->profiles();
        $session = CashSession::create([
            'organization_id' => $users['finance']->organizations()->value('organizations.id'),
            'user_id' => $users['seller']->id,
            'opened_at' => now(),
            'opening_float' => 20,
        ]);

        $this->actingAs($users['finance'])->get(route('company.cash-close.index'))
            ->assertOk()
            ->assertSee('Cierre de caja')
            ->assertDontSee('Abrir turno')
            ->assertDontSee('Cerrar turno');
        $this->actingAs($users['finance'])->post(route('company.cash-close.open'), [
            'opening_float' => 10,
        ])->assertForbidden();
        $this->actingAs($users['finance'])->post(route('company.cash-close.close', $session), [
            'counted_cash' => 20,
        ])->assertForbidden();
        $this->actingAs($users['finance'])->post(route('company.pos.void', 999999))->assertForbidden();
        $this->assertNull($session->fresh()->closed_at);
    }

    public function test_operator_cannot_open_money_screens(): void
    {
        $operator = $this->profiles()['operator'];

        $this->actingAs($operator)->get(route('company.pos.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.sales.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.cash-close.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.commissions.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.collections.index'))->assertForbidden();
    }

    public function test_a_company_cannot_read_another_companys_operation_by_changing_the_id(): void
    {
        $users = $this->profiles();
        $other = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Ajena Perfil SAC', 'ruc' => '20999999077',
            'email' => 'ajena-perfil@test.pe', 'status' => 'active', 'modality' => 'fluvial',
        ]);
        $origin = Port::create(['name' => 'Origen perfil', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $destination = Port::create(['name' => 'Destino perfil', 'city' => 'Nauta', 'region' => 'Loreto', 'modality' => 'fluvial', 'is_active' => true]);
        $vessel = Vessel::create([
            'organization_id' => $other->id, 'name' => 'Ajena Perfil', 'registration_number' => 'AP-1',
            'vessel_type' => 'lancha', 'seat_capacity' => 4, 'status' => 'ready',
        ]);
        $route = TransportRoute::create([
            'organization_id' => $other->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'code' => 'AP-1',
        ]);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now(),
            'fare' => 50, 'status' => 'scheduled', 'is_published' => true,
        ]);
        $foreign = User::factory()->create(['name' => 'Cajero Ajeno Perfil']);
        $foreign->organizations()->attach($other->id, ['status' => 'active']);
        $session = CashSession::create([
            'organization_id' => $other->id, 'user_id' => $foreign->id, 'opened_at' => now(), 'opening_float' => 15,
        ]);

        $this->actingAs($users['operator'])->get(route('company.boarding.show', $departure))->assertForbidden();
        $this->actingAs($users['finance'])->get(route('company.cash-close.print', $session))->assertForbidden();
        $this->actingAs($users['finance'])->get(route('company.cash-close.index'))->assertOk()->assertDontSee('Cajero Ajeno Perfil');
        $this->actingAs($users['finance'])->post(route('company.cash-close.close', $session), [
            'counted_cash' => 15,
        ])->assertForbidden();
    }

    public function test_the_roles_screen_is_a_read_only_matrix_for_the_admin(): void
    {
        $users = $this->profiles();

        $this->actingAs($users['admin'])->get(route('company.roles.index'))
            ->assertOk()
            ->assertSee('Perfiles y permisos')
            ->assertSee('Administrador')
            ->assertSee('Vendedor')
            ->assertSee('Operador')
            ->assertSee('Finanzas')
            ->assertSee('Activo')
            ->assertSee('Invitado')
            ->assertSee('Suspendido')
            ->assertDontSee('En preparación');

        $this->actingAs($users['seller'])->get(route('company.roles.index'))->assertForbidden();
        $this->actingAs($users['operator'])->get(route('company.roles.index'))->assertForbidden();
        $this->actingAs($users['finance'])->get(route('company.roles.index'))->assertForbidden();
    }

    /**
     * @return array<string, User>
     */
    private function profiles(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Perfiles SAC', 'commercial_name' => 'Perfiles',
            'ruc' => '20999999066', 'email' => 'perfiles@test.pe', 'status' => 'active', 'modality' => 'mixto',
        ]);
        $agency = Agency::create([
            'organization_id' => $organization->id, 'name' => 'Agencia Perfil', 'ruc' => '20999999055',
            'contact_name' => 'Luz Perfil', 'phone' => '065600200', 'status' => 'active',
            'max_surcharge_percent' => 0,
        ]);

        return [
            'admin' => $this->member($organization, 'company_admin', 'Administrador'),
            'seller' => $this->member($organization, 'company_counter', 'Vendedor'),
            'agency' => $this->member($organization, 'company_counter', 'Vendedor', $agency->id),
            'operator' => $this->member($organization, 'company_operator', 'Operador'),
            'finance' => $this->member($organization, 'company_finance', 'Finanzas'),
        ];
    }

    private function member(Organization $organization, string $code, string $name, ?int $agencyId = null): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'agency_id' => $agencyId,
        ]);
        $user->roles()->attach(
            Role::firstOrCreate(['code' => $code], ['name' => $name])->id,
            ['organization_id' => $organization->id],
        );
        $user->organizations()->attach($organization->id, ['status' => 'active']);

        return $user;
    }
}
