<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sitemap_lists_public_pages_and_active_companies_only(): void
    {
        $active = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Río Activo SAC', 'ruc' => '20699000001', 'status' => 'active',
        ]);
        $pending = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Río Pendiente SAC', 'ruc' => '20699000002', 'status' => 'pending',
        ]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $response->assertSee(route('home'), false)
            ->assertSee(route('routes.index'), false)
            ->assertSee(route('claims.create'), false)
            ->assertSee(route('companies.show', $active), false)
            ->assertDontSee(route('companies.show', $pending), false)
            ->assertDontSee('/admin', false)
            ->assertDontSee('/empresa/', false)
            ->assertDontSee('/mis-viajes', false);
    }

    public function test_robots_keeps_search_engines_out_of_private_areas_and_points_to_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /empresa/', false)
            ->assertSee('Disallow: /mis-viajes', false)
            ->assertSee('Sitemap: '.route('sitemap'), false);
    }

    public function test_registration_and_card_payment_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/register', []);
        }
        $this->post('/register', [])->assertStatus(429);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('payments.culqi.charge', 'NO-EXISTE'), []);
        }
        $this->post(route('payments.culqi.charge', 'NO-EXISTE'), [])->assertStatus(429);
    }

    public function test_login_password_reset_and_claims_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', []);
        }
        $this->post('/login', [])->assertStatus(429);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/forgot-password', []);
        }
        $this->post('/forgot-password', [])->assertStatus(429);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post('/libro-de-reclamaciones', []);
        }
        $this->post('/libro-de-reclamaciones', [])->assertStatus(429);
    }

    public function test_public_pages_link_the_icon_and_the_web_manifest(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true);

        $this->assertSame('NavegaYA', $manifest['name'] ?? null);
        $this->assertSame('/favicon.ico', $manifest['icons'][0]['src'] ?? null);
        $this->assertFileExists(public_path('favicon.ico'));

        $this->get('/')
            ->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee('rel="manifest"', false)
            ->assertSee('site.webmanifest', false)
            ->assertDontSee('APP_DEBUG', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee('<label', false);
    }

    public function test_too_many_attempts_and_maintenance_have_branded_pages(): void
    {
        $this->assertStringContainsString('Demasiados intentos', view('errors.429')->render());
        $this->assertStringContainsString('Estamos en mantenimiento', view('errors.503')->render());
        $this->assertStringContainsString('Página no encontrada', view('errors.404')->render());
        $this->assertStringContainsString('La sesión expiró', view('errors.419')->render());
        $this->assertStringContainsString('No pudimos completar la solicitud', view('errors.500')->render());

        foreach (['404', '419', '429', '500', '503'] as $code) {
            $html = view('errors.'.$code)->render();
            $this->assertStringNotContainsString('Stack trace', $html);
            $this->assertStringNotContainsString('vendor/laravel', $html);
        }
    }
}
