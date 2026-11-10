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

        $organization->update([
            'public_presentation' => [
                'menu' => ['bar' => '#112233', 'stroke' => '#abcdef'],
                'cargo' => ['mediano' => 10],
            ],
            'whatsapp' => '51987654321',
            'whatsapp_message' => 'Hola, conservado.',
            'show_public_whatsapp' => true,
            'cover_image_path' => '/storage/covers/original.webp',
        ]);

        $this->actingAs($admin)->get(route('company.profile.edit'))
            ->assertOk()
            ->assertDontSee('Colores del menú')
            ->assertDontSee('name="menu_bar"', false)
            ->assertDontSee('name="whatsapp"', false)
            ->assertDontSee('name="cover"', false)
            ->assertSee('Tarifas referenciales de encomienda')
            ->assertSee('Se muestran en la pestaña Encomiendas y carga de tu página')
            ->assertSee('Cambiar portada y fotos en Galería')
            ->assertSee('El WhatsApp se configura en WhatsApp de atención')
            ->assertSee('Pequeño')
            ->assertSee('Mediano')
            ->assertSee('Grande');

        $this->actingAs($admin)->patch(route('company.profile.update'), [
            'menu_bar' => '#000000',
            'whatsapp' => '51900000000',
            'whatsapp_message' => 'cambiado',
            'show_public_whatsapp' => '0',
            'cargo_mediano' => '40',
            'cargo_port_note' => 'Desde 25 kg se cotiza en el muelle de la empresa.',
        ])->assertRedirect();

        $saved = $organization->fresh();
        $this->assertSame('#112233', $saved->public_presentation['menu']['bar']);
        $this->assertSame('#abcdef', $saved->public_presentation['menu']['stroke']);
        $this->assertEquals(40, $saved->public_presentation['cargo']['mediano']);
        $this->assertSame('51987654321', $saved->whatsapp);
        $this->assertSame('Hola, conservado.', $saved->whatsapp_message);
        $this->assertTrue($saved->show_public_whatsapp);
        $this->assertSame('/storage/covers/original.webp', $saved->cover_image_path);
        $this->assertSame('published', $saved->profile_content_status);

        $this->actingAs($otherAdmin)->patch(route('company.profile.update'), [
            'organization_id' => $organization->id,
            'cargo_mediano' => '9',
        ])->assertRedirect();

        $this->assertEquals(40, $organization->fresh()->public_presentation['cargo']['mediano']);
        $this->assertEquals(9, $other->fresh()->public_presentation['cargo']['mediano']);
        $this->assertArrayNotHasKey('menu', $other->fresh()->public_presentation);

        $this->get(route('companies.show', $organization))
            ->assertOk()
            ->assertSee('--menu-bar:#112233', false)
            ->assertSee('data-price-mediano="40.00"', false)
            ->assertSee('data-port-note="Desde 25 kg se cotiza en el muelle de la empresa."', false);
    }

    public function test_menu_colors_posted_with_the_profile_are_ignored(): void
    {
        [$admin, $organization] = $this->companyAdmin('Operador Marca', '20633333333');
        $organization->update([
            'public_presentation' => ['menu' => ['bar' => '#112233', 'stroke' => '#123456']],
        ]);

        $this->actingAs($admin)->patch(route('company.profile.update'), [
            'menu_bar' => 'verde',
            'menu_stroke' => '',
            'cargo_mediano' => '10',
        ])->assertRedirect();

        $saved = $organization->fresh()->public_presentation;
        $this->assertSame('#112233', $saved['menu']['bar']);
        $this->assertSame('#123456', $saved['menu']['stroke']);
        $this->get(route('companies.show', $organization))
            ->assertOk()
            ->assertSee('--menu-bar:#112233', false)
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
