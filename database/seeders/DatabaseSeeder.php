<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Role::upsert([
            ['code' => 'super_admin', 'name' => 'Administrador principal', 'description' => 'Gestiona toda la plataforma NavegaYA.'],
            ['code' => 'company_admin', 'name' => 'Administrador de empresa', 'description' => 'Gestiona la empresa de transporte, flota, rutas y ventas.'],
            ['code' => 'company_counter', 'name' => 'Counter / despachador', 'description' => 'Atiende embarque, pasajeros y carga únicamente para su empresa.'],
            ['code' => 'company_seller', 'name' => 'Vendedor de empresa', 'description' => 'Registra ventas presenciales de su empresa.'],
            ['code' => 'boarding_agent', 'name' => 'Agente de embarque', 'description' => 'Valida boletos QR durante el embarque.'],
            ['code' => 'agency_agent', 'name' => 'Agente de agencia', 'description' => 'Vende pasajes autorizados sin el cargo web de NavegaYA.'],
            ['code' => 'customer', 'name' => 'Cliente / pasajero', 'description' => 'Compra pasajes y consulta únicamente sus propios boletos y códigos QR.'],
            ['code' => 'passenger', 'name' => 'Pasajero (legado)', 'description' => 'Alias compatible para cuentas de pasajeros creadas anteriormente.'],
        ], ['code'], ['name', 'description']);

        $this->call(LoretoGeographySeeder::class);
        $this->call(AirOperatorSeeder::class);
        $this->call(MarketplaceDashboardSeeder::class);
    }
}
