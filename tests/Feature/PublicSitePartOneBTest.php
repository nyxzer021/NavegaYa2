<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SiteHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSitePartOneBTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_button_is_hidden_without_an_enabled_number(): void
    {
        $company = new Organization(['commercial_name' => 'Operador', 'whatsapp' => null, 'show_whatsapp' => true]);
        $this->blade('<x-company-whatsapp :company="$company" />', compact('company'))->assertDontSee('Consultar por WhatsApp');
    }

    public function test_shared_hero_uses_uploaded_image_or_default(): void
    {
        $this->blade('<x-amazon-hero-bg theme="routes" />')->assertSee('images/navegaya-hero-amazonas.png', false);
        SiteHero::create(['page_key' => 'routes', 'image_path' => '/storage/site-heroes/custom.webp', 'focal_point' => 'top']);
        $this->blade('<x-amazon-hero-bg theme="routes" />')->assertSee('/storage/site-heroes/custom.webp', false)->assertSee('object-top', false);
    }
}
