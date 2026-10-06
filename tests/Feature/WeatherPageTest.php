<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeatherPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_loreto_weather_page_defaults_to_iquitos_and_is_in_menu(): void
    {
        $this->get(route('weather.index'))->assertOk()->assertSee('Clima en Loreto')->assertSee('Iquitos, Loreto')->assertSee('api.open-meteo.com', false);
        $this->assertDatabaseMissing('navigation_items', ['target' => 'weather.index']);
        $this->get(route('home'))->assertOk()->assertSee('Iquitos')->assertSee('28°C')->assertSee('Nivel del río: Normal');
    }
}
