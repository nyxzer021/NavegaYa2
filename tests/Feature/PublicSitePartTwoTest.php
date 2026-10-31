<?php

namespace Tests\Feature;

use App\Models\DestinationCity;
use App\Models\MasterRoute;
use App\Models\Port;
use App\Models\PublicPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSitePartTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_counts_active_master_routes_touching_a_city(): void
    {
        DestinationCity::create(['name' => 'Lagunas', 'department' => 'Loreto', 'is_active' => true, 'show_in_discover' => true, 'sort_order' => 1]);
        $origin = Port::create(['name' => 'Puerto Lagunas', 'city' => 'Lagunas', 'district' => 'Lagunas', 'province' => 'Alto Amazonas', 'region' => 'Loreto', 'modality' => 'fluvial', 'port_type' => 'puerto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto Pebas', 'city' => 'Pebas', 'district' => 'Pebas', 'province' => 'Mariscal Ramón Castilla', 'region' => 'Loreto', 'modality' => 'fluvial', 'port_type' => 'puerto', 'is_active' => true]);
        MasterRoute::create(['code' => 'TEST-1', 'modality' => 'fluvial', 'origin_city' => 'Lagunas', 'destination_city' => 'Pebas', 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'estimated_duration_text' => '4 h 40 min', 'estimated_duration_minutes' => 280, 'status' => 'active']);

        $this->get('/descubrir')->assertOk()->assertSee('1 ruta registrada');
    }

    public function test_duration_is_formatted_from_minutes(): void
    {
        $this->assertSame('4 h 40 min', MasterRoute::formatDuration(280));
    }

    public function test_english_page_falls_back_to_complete_spanish_content(): void
    {
        PublicPage::create(['title' => 'Términos y condiciones', 'slug' => 'terminos-prueba', 'summary' => 'Resumen completo', 'content' => 'Contenido completo en español.', 'is_published' => true]);
        $this->withSession(['public_locale' => 'en'])->get('/pagina/terminos-prueba')->assertOk()->assertSee('Available in Spanish only')->assertSee('Contenido completo en español.');
    }
}
