<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Organization;
use App\Models\OrganizationSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhereToBuyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_lists_only_active_sites_and_agencies_of_that_company(): void
    {
        $company = $this->company('Río Bellavista', '20631000031');
        OrganizationSite::create([
            'organization_id' => $company->id,
            'name' => 'Muelle Bellavista',
            'kind' => 'dock',
            'address' => 'Malecón Tarapacá 10',
            'city' => 'Iquitos',
            'hours' => '06:00 a 18:00',
            'phone' => '965111222',
            'is_active' => true,
            'show_on_where_to_buy' => true,
        ]);
        OrganizationSite::create([
            'organization_id' => $company->id,
            'name' => 'Oficina Punchana',
            'kind' => 'office',
            'address' => 'Av. La Marina 20',
            'city' => 'Punchana',
            'hours' => '09:00 a 13:00',
            'phone' => '965111333',
            'is_active' => true,
            'show_on_where_to_buy' => true,
        ]);
        OrganizationSite::create([
            'organization_id' => $company->id,
            'name' => 'Muelle sin datos',
            'kind' => 'dock',
            'address' => 'Puerto sin horario',
            'city' => 'Iquitos',
            'is_active' => true,
            'show_on_where_to_buy' => true,
        ]);
        OrganizationSite::create([
            'organization_id' => $company->id,
            'name' => 'Muelle cerrado',
            'kind' => 'dock',
            'address' => 'Calle cerrada',
            'city' => 'Iquitos',
            'is_active' => false,
            'show_on_where_to_buy' => true,
        ]);
        OrganizationSite::create([
            'organization_id' => $company->id,
            'name' => 'Oficina interna',
            'kind' => 'office',
            'address' => 'Calle interna',
            'city' => 'Iquitos',
            'is_active' => true,
            'show_on_where_to_buy' => false,
        ]);
        OrganizationSite::create([
            'organization_id' => $company->id,
            'name' => 'Muelle oculto',
            'kind' => 'dock',
            'address' => 'Calle oculta',
            'city' => 'Iquitos',
            'is_active' => true,
            'show_on_where_to_buy' => true,
            'hidden_by_admin' => true,
        ]);
        Agency::create([
            'organization_id' => $company->id,
            'name' => 'Agencia Nanay',
            'ruc' => '20999111001',
            'contact_name' => 'Luz Secreta',
            'phone' => '965333444',
            'status' => 'active',
            'max_surcharge_percent' => 12.50,
        ]);
        Agency::create([
            'organization_id' => $company->id,
            'name' => 'Agencia suspendida',
            'ruc' => '20999111002',
            'contact_name' => 'Nadie',
            'phone' => '965333555',
            'status' => 'suspended',
        ]);
        $other = $this->company('Otra Flota', '20631000032');
        Agency::create([
            'organization_id' => $other->id,
            'name' => 'Agencia ajena',
            'ruc' => '20999111003',
            'contact_name' => 'Ajena',
            'phone' => '965444555',
            'status' => 'active',
        ]);

        $html = $this->get(route('companies.show', $company))->assertOk()->getContent();

        $this->assertStringContainsString('Dónde comprar', $html);
        $this->assertStringContainsString('data-open="donde-comprar"', $html);
        $this->assertStringContainsString('id="donde-comprar"', $html);
        $this->assertStringContainsString('Muelle Bellavista', $html);
        $this->assertStringContainsString('Oficina Punchana', $html);
        $this->assertStringContainsString('Sucursal', $html);
        $this->assertStringContainsString('Agencia Nanay', $html);
        $this->assertStringContainsString('06:00 a 18:00', $html);
        $this->assertStringContainsString('https://wa.me/51965111222', $html);
        $this->assertStringContainsString('https://wa.me/51965333444', $html);
        $this->assertStringContainsString('https://www.google.com/maps/search/?api=1&query=', $html);
        $this->assertStringContainsString('Cómo llegar', $html);
        $this->assertStringNotContainsString('Muelle cerrado', $html);
        $this->assertStringNotContainsString('Oficina interna', $html);
        $this->assertStringNotContainsString('Muelle oculto', $html);
        $this->assertStringNotContainsString('Agencia suspendida', $html);
        $this->assertStringNotContainsString('Agencia ajena', $html);
        $this->assertStringNotContainsString('20999111001', $html);
        $this->assertStringNotContainsString('Luz Secreta', $html);
        $this->assertStringNotContainsString('12.50', $html);
        $this->assertStringNotContainsString('965333555', $html);

        $sparse = $this->card($html, 'Muelle sin datos');
        $this->assertStringNotContainsString('Horario', $sparse);
        $this->assertStringNotContainsString('Teléfono', $sparse);
        $this->assertStringNotContainsString('wa.me', $sparse);
        $this->assertStringContainsString('Cómo llegar', $sparse);

        $agency = $this->card($html, 'Agencia Nanay');
        $this->assertStringNotContainsString('Cómo llegar', $agency);
        $this->assertStringContainsString('https://wa.me/51965333444', $agency);
    }

    public function test_where_to_buy_stays_hidden_when_the_company_has_no_public_points(): void
    {
        $company = $this->company('Sin puntos', '20631000033');
        OrganizationSite::create([
            'organization_id' => $company->id,
            'name' => 'Muelle inactivo',
            'kind' => 'dock',
            'address' => 'Nadie pasa',
            'city' => 'Iquitos',
            'is_active' => false,
        ]);
        Agency::create([
            'organization_id' => $company->id,
            'name' => 'Agencia pausada',
            'ruc' => '20999111004',
            'contact_name' => 'Pausa',
            'phone' => '965555666',
            'status' => 'suspended',
        ]);

        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertDontSee('Dónde comprar')
            ->assertDontSee('data-open="donde-comprar"', false)
            ->assertDontSee('Muelle inactivo')
            ->assertDontSee('Agencia pausada')
            ->assertDontSee('20999111004');
    }

    private function company(string $name, string $ruc): Organization
    {
        return Organization::create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'commercial_name' => $name,
            'ruc' => $ruc,
            'email' => strtolower(str_replace(' ', '', $name)).'@test.pe',
            'status' => 'active',
        ]);
    }

    private function card(string $html, string $name): string
    {
        $start = strpos($html, $name);
        $this->assertNotFalse($start);
        $end = strpos($html, '</article>', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }
}
