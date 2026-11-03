<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPublicPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_saves_menu_color_and_cargo_price_for_its_own_profile(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Color', '20611111111');
        [$otherAdmin, $other] = $this->companyAdmin('Operador Ajeno', '20622222222');

        $this->actingAs($admin)->get(route('company.profile.edit'))
            ->assertOk()
            ->assertSee('Colores del menú')
            ->assertSee('Volver a los colores de NavegaYA')
            ->assertSee('Encomiendas')
            ->assertSee('Pequeño')
            ->assertSee('Mediano')
            ->assertSee('Grande');

        $this->actingAs($admin)->patch(route('company.profile.update'), [
            'menu_bar' => '#112233',
            'cargo_mediano' => '40',
            'cargo_port_note' => 'Desde 25 kg se cotiza en el muelle de la empresa.',
        ])->assertRedirect();

        $saved = $organization->fresh()->public_presentation;
        $this->assertSame('#112233', $saved['menu']['bar']);
        $this->assertEquals(40, $saved['cargo']['mediano']);
        $this->assertSame('published', $organization->fresh()->profile_content_status);

        $this->actingAs($otherAdmin)->patch(route('company.profile.update'), [
            'organization_id' => $organization->id,
            'menu_bar' => '#abcdef',
            'cargo_mediano' => '9',
        ])->assertRedirect();

        $this->assertSame('#112233', $organization->fresh()->public_presentation['menu']['bar']);
        $this->assertEquals(40, $organization->fresh()->public_presentation['cargo']['mediano']);
        $this->assertSame('#abcdef', $other->fresh()->public_presentation['menu']['bar']);

        $this->get(route('companies.show', $organization))
            ->assertOk()
            ->assertSee('--menu-bar:#112233', false)
            ->assertSee('data-price-mediano="40.00"', false)
            ->assertSee('data-port-note="Desde 25 kg se cotiza en el muelle de la empresa."', false);
    }

    public function test_blank_or_invalid_menu_color_does_not_replace_the_brand_color(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Marca', '20633333333');

        $this->actingAs($admin)->patch(route('company.profile.update'), [
            'menu_bar' => 'verde',
            'cargo_mediano' => '10',
        ])->assertSessionHasErrors('menu_bar');
        $this->assertNull($organization->fresh()->public_presentation);

        $this->actingAs($admin)->patch(route('company.profile.update'), [
            'menu_bar' => '',
            'menu_stroke' => '#123456',
        ])->assertRedirect();

        $this->get(route('companies.show', $organization))
            ->assertOk()
            ->assertSee('--menu-bar:#062c21', false)
            ->assertSee('--menu-stroke:#123456', false);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function companyAdmin(string $name, string $ruc): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'ruc' => $ruc,
            'email' => strtolower(str_replace(' ', '', $name)).'@test.pe',
            'status' => 'active',
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }
}
