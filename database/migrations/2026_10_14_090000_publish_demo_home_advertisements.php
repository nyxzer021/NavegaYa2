<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $campaigns = [
            [
                'business_name' => 'Lodge Río Marañón',
                'business_type' => 'lodge_hotel',
                'category' => 'Lodge / hotel',
                'title' => 'Descansa frente al río y despierta en la selva',
                'description' => 'Alojamiento amazónico con desayuno, traslado coordinado y experiencias junto al río.',
                'city_destination' => 'Nauta',
                'image_url' => '/images/branding/banner-guia-loreto.webp',
                'target_url' => 'https://wa.me/51900000000',
            ],
            [
                'business_name' => 'Sabores del Itaya',
                'business_type' => 'restaurant',
                'category' => 'Restaurante',
                'title' => 'Sabores amazónicos a pocos minutos del puerto',
                'description' => 'Pescados de río, cocina regional y atención para viajeros, familias y grupos.',
                'city_destination' => 'Iquitos',
                'image_url' => '/images/branding/banner-puertos-nauta.webp',
                'target_url' => 'https://wa.me/51900000000',
            ],
            [
                'business_name' => 'Amazonía Viva Tours',
                'business_type' => 'tour_operator',
                'category' => 'Operador de tours',
                'title' => 'Explora Loreto con guías locales',
                'description' => 'Excursiones de naturaleza, navegación y cultura amazónica con salidas coordinadas.',
                'city_destination' => 'Iquitos',
                'image_url' => '/images/branding/banner-home-viajeros-amazonia.webp',
                'target_url' => 'https://wa.me/51900000000',
            ],
        ];

        foreach ($campaigns as $campaign) {
            DB::table('advertisements')->updateOrInsert(
                ['business_name' => $campaign['business_name']],
                $campaign + [
                    'contact_name' => 'Área comercial',
                    'phone_whatsapp' => '+51 900 000 000',
                    'email' => null,
                    'ruc' => null,
                    'placements' => json_encode(['home_hero']),
                    'starts_on' => today(),
                    'ends_on' => today()->addMonths(6),
                    'monthly_fee' => 300,
                    'status' => 'active',
                    'admin_notes' => 'Contenido demostrativo registrado para validar la portada.',
                    'views' => 0,
                    'clicks' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('advertisements')->whereIn('business_name', [
            'Lodge Río Marañón',
            'Sabores del Itaya',
            'Amazonía Viva Tours',
        ])->delete();
    }
};
