<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_policies_are_published_and_linked_from_the_footer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Privacidad y datos personales')
            ->assertSee('Cookies')
            ->assertSee('Seguridad digital')
            ->assertSee('open-legal-policy')
            ->assertSee('Información legal de NavegaYA')
            ->assertSee('Política de privacidad y protección de datos personales')
            ->assertSee('Cerrar')
            ->assertDontSee('Abrir en página completa');

        foreach (['privacidad', 'terminos', 'cookies', 'seguridad'] as $slug) {
            $this->get(route('pages.show', $slug))->assertOk();
        }
    }

    public function test_company_registration_links_legal_terms(): void
    {
        $this->get(route('company.registration'))
            ->assertOk()
            ->assertSee(route('pages.show', 'terminos'), false)
            ->assertSee(route('pages.show', 'privacidad'), false);
    }
}
