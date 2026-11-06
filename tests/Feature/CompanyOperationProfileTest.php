<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\OrganizationModality;
use App\Models\OrganizationSite;
use App\Models\Port;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyOperationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_helper_normalizes_peruvian_mobiles_and_rejects_the_placeholder(): void
    {
        $this->assertSame('51953540705', PhoneNumber::peru('953540705'));
        $this->assertSame('51953540705', PhoneNumber::peru('+51 953 540 705'));
        $this->assertSame('+51 953 540 705', PhoneNumber::display('953540705'));
        $this->assertNull(PhoneNumber::peru('12345'));
        $this->assertNull(PhoneNumber::peru('51900000000'));
    }

    public function test_fluvial_company_does_not_see_air_options_until_approval(): void
    {
        [$admin, $organization] = $this->companyAdmin();
        OrganizationModality::create([
            'organization_id' => $organization->id, 'code' => 'fluvial', 'status' => 'approved',
        ]);
        OrganizationModality::create([
            'organization_id' => $organization->id, 'code' => 'aereo', 'status' => 'requested',
            'permit_number' => 'MTC-1', 'rejection_reason' => null,
        ]);

        $this->actingAs($admin)->get(route('company.dashboard'))
            ->assertOk()->assertSee('Fluvial')->assertDontSee('Aéreo');
        $this->actingAs($admin)->get(route('company.fleet.index'))
            ->assertOk()->assertSee('Lanchas y motonaves')->assertDontSee('Avionetas')->assertDontSee('Aeronaves');

        $super = $this->superAdmin();
        $air = $organization->modalities()->where('code', 'aereo')->firstOrFail();
        $this->actingAs($super)->patch(route('admin.companies.modalities.review', [$organization, $air]), [
            'decision' => 'approved', 'reason' => 'Permiso vigente',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('company.details.index'))
            ->assertOk()->assertSee('Aprobada')->assertSee('Aéreo');

        $this->actingAs($super)->patch(route('admin.companies.modalities.review', [$organization, $air->fresh()]), [
            'decision' => 'rejected', 'reason' => 'El permiso está vencido',
        ])->assertRedirect();
        $this->actingAs($admin)->get(route('company.details.index'))
            ->assertOk()->assertSee('El permiso está vencido');
        $this->assertSame('fluvial', $organization->fresh()->modality);
    }

    public function test_a_company_cannot_edit_another_companys_site(): void
    {
        [$admin, $organization] = $this->companyAdmin();
        [, $other] = $this->companyAdmin('Otra Empresa SAC', '20999999002', 'otra@test.pe');
        $site = OrganizationSite::create([
            'organization_id' => $other->id, 'name' => 'Muelle ajeno', 'kind' => 'dock',
            'address' => 'Malecón', 'city' => 'Iquitos', 'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('company.sites.update', $site), [
            'name' => 'Tomada', 'kind' => 'office', 'address' => 'Otra', 'city' => 'Nauta', 'is_active' => '1',
        ])->assertNotFound();
        $this->assertSame('Muelle ajeno', $site->fresh()->name);
        $this->assertSame($other->id, $site->fresh()->organization_id);
        $this->actingAs($admin)->post(route('company.sites.store'), [
            'name' => 'Muelle propio', 'kind' => 'dock', 'address' => 'Puerto', 'city' => 'Iquitos', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('organization_sites', ['organization_id' => $organization->id, 'name' => 'Muelle propio']);
        $this->assertDatabaseMissing('organization_sites', ['organization_id' => $other->id, 'name' => 'Muelle propio']);
    }

    public function test_whatsapp_screen_normalizes_numbers_and_blocks_the_seller(): void
    {
        [$admin, $organization] = $this->companyAdmin();
        $seller = User::factory()->create();
        $seller->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor'])->id, ['organization_id' => $organization->id]);
        $seller->organizations()->attach($organization->id, ['status' => 'active']);

        $this->actingAs($seller)->get(route('company.whatsapp.index'))->assertForbidden();
        $this->actingAs($seller)->patch(route('company.whatsapp.update'), ['whatsapp' => '953540705'])->assertForbidden();

        $this->actingAs($admin)->patch(route('company.whatsapp.update'), [
            'whatsapp' => '12345',
        ])->assertSessionHasErrors('whatsapp');

        $this->actingAs($admin)->patch(route('company.whatsapp.update'), [
            'whatsapp' => '+51 953 540 705',
            'whatsapp_message' => 'Hola, quiero un pasaje.',
            'show_public_whatsapp' => '1',
        ])->assertRedirect();
        $this->assertSame('51953540705', $organization->fresh()->whatsapp);
        $this->assertNull($organization->fresh()->whatsapp_cargo);
        $this->actingAs($admin)->get(route('company.whatsapp.index'))->assertOk()->assertSee('Último cambio');

        $this->get(route('companies.show', $organization))
            ->assertOk()
            ->assertSee('https://wa.me/51953540705', false)
            ->assertSee('+51 953 540 705');

        $super = $this->superAdmin();
        $this->actingAs($super)->patch(route('admin.companies.whatsapp.visibility', $organization), [
            'hidden' => '1', 'reason' => 'Reporte de fraude',
        ])->assertRedirect();
        $this->get(route('companies.show', $organization))->assertOk()->assertDontSee('https://wa.me/51953540705', false);
    }

    public function test_menu_card_and_public_page_show_the_chosen_base_port(): void
    {
        [$admin, $organization] = $this->companyAdmin();
        $port = Port::create(['name' => 'Puerto Prueba', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $organization->update(['base_port_id' => $port->id, 'base_city' => 'Nauta']);

        $this->actingAs($admin)->get(route('company.dashboard'))->assertOk()->assertSee('Puerto Prueba · Iquitos');
        $this->get(route('companies.show', $organization))->assertOk()->assertSee('Puerto Prueba · Iquitos');
    }

    public function test_public_pages_do_not_link_the_placeholder_number(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('51900000000', false);
        $this->get(route('guide.index'))->assertOk()->assertDontSee('51900000000', false);
        $this->get(route('advertising.create'))->assertOk()->assertDontSee('51900000000', false);
    }

    public function test_super_admin_inbox_lists_requested_modalities_and_expiring_documents(): void
    {
        [$admin, $organization] = $this->companyAdmin();
        OrganizationModality::create([
            'organization_id' => $organization->id, 'code' => 'aereo_fluvial', 'status' => 'requested',
            'permit_number' => 'DGAC-9', 'permit_expires_on' => now()->addDays(10),
        ]);
        OrganizationDocument::create([
            'organization_id' => $organization->id, 'type' => 'ruc', 'path' => 'docs/ruc.pdf',
            'original_name' => 'ruc.pdf', 'expires_on' => now()->addDays(12),
        ]);

        $this->actingAs($this->superAdmin())->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Modalidades por aprobar')
            ->assertSee('Documentos por vencer');
    }

    public function test_threshold_settings_are_saved_and_not_required_for_a_quote(): void
    {
        $this->actingAs($this->superAdmin())->patch(route('admin.settings.thresholds'), [
            'baggage_allowance_river_kg' => 50,
            'baggage_allowance_air_kg' => 10,
            'parcel_weight_threshold_kg' => 50,
        ])->assertRedirect();

        $this->assertSame('50', SystemSetting::value('baggage_allowance_river_kg'));
        $this->assertSame('10', SystemSetting::value('baggage_allowance_air_kg'));
        $this->assertSame('50', SystemSetting::value('parcel_weight_threshold_kg'));
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function companyAdmin(string $legal = 'Operador Datos SAC', string $ruc = '20999999001', string $email = 'datos@test.pe'): array
    {
        $organization = Organization::create([
            'type' => 'transport_company', 'legal_name' => $legal, 'commercial_name' => 'Datos Fast',
            'ruc' => $ruc, 'email' => $email, 'status' => 'active', 'base_city' => 'Iquitos', 'modality' => 'fluvial',
        ]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador'])->id);

        return $admin;
    }
}
