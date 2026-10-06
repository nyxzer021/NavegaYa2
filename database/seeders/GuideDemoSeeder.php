<?php

namespace Database\Seeders;

use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class GuideDemoSeeder extends Seeder
{
    public function run(): void
    {
        $phone = SystemSetting::value('whatsapp_number', '51900000000');
        $cities = collect([
            ['name' => 'Iquitos', 'river' => 'Amazonas'],
            ['name' => 'Nauta', 'river' => 'Marañón'],
            ['name' => 'Yurimaguas', 'river' => 'Huallaga'],
            ['name' => 'Contamana', 'river' => 'Ucayali'],
        ])->mapWithKeys(function ($data) {
            $city = DestinationCity::firstOrCreate(['name' => $data['name']], $data + ['department' => 'Loreto', 'is_active' => true, 'sort_order' => 50]);

            return [$data['name'] => $city];
        });

        $items = [
            ['Nauta', 'lodging', 'Ecolodge Río Marañón · Demostración', 'Ecolodge y selva', 'Bungalows junto al río, excursiones interpretativas y alimentación regional.', 'Embarcadero principal de Nauta', 'Paquetes desde S/ 280', '/images/navegaya-hero-rio.png', true],
            ['Iquitos', 'lodging', 'Casa Río Airbnb · Demostración', 'Airbnb y alojamiento', 'Casa equipada para estadías cortas, cerca del malecón y con coordinación de traslado.', 'Centro de Iquitos', 'Desde S/ 120 por noche', '/images/navegaya-hero-amazonas.png', false],
            ['Iquitos', 'gastronomy', 'Sabores del Itaya · Demostración', 'Restaurante amazónico', 'Patarashca, paiche, juanes y bebidas regionales en un ambiente familiar.', 'Zona del malecón', 'Platos desde S/ 25', '/images/navegaya-hero-atardecer.png', true],
            ['Yurimaguas', 'lodging', 'Hotel Puerto Huallaga · Demostración', 'Hotel urbano', 'Habitaciones climatizadas, desayuno y orientación para conexiones fluviales.', 'Cerca del Puerto La Boca', 'Desde S/ 95 por noche', '/images/navegaya-hero-rio.png', false],
            ['Contamana', 'attraction', 'Ruta Aguas Calientes · Demostración', 'Naturaleza y trekking', 'Recorrido guiado de naturaleza con coordinación de movilidad local y horarios flexibles.', 'Salida desde Contamana', 'Desde S/ 80', '/images/navegaya-hero-atardecer.png', true],
            ['Nauta', 'attraction', 'Expedición Pacaya Samiria · Demostración', 'Tour de naturaleza', 'Navegación, observación de fauna y acompañamiento de guías locales.', 'Salida desde Nauta', 'Consultar programa', '/images/navegaya-hero-amazonas.png', false],
        ];

        foreach ($items as $index => [$city, $type, $name, $category, $description, $address, $price, $image, $featured]) {
            $listing = DestinationListing::updateOrCreate(['destination_city_id' => $cities[$city]->id, 'name' => $name], [
                'type' => $type, 'category' => $category, 'description' => $description, 'address' => $address,
                'contact_phone' => $phone, 'price_reference' => $price, 'opening_hours' => 'Atención previa coordinación',
                'is_featured' => $featured, 'is_active' => true, 'sort_order' => $index + 1,
            ]);
            $listing->images()->updateOrCreate(['sort_order' => 1], ['path' => $image, 'is_cover' => true]);
        }
    }
}
