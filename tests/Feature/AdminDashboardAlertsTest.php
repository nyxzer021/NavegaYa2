<?php

namespace Tests\Feature;

use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_warns_when_support_whatsapp_is_missing(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('El WhatsApp de atención no está configurado');
    }

    public function test_dashboard_hides_the_whatsapp_warning_when_the_number_exists(): void
    {
        SystemSetting::create(['key' => 'whatsapp_number', 'value' => '999111222']);

        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('El WhatsApp de atención no está configurado');
    }

    public function test_dashboard_warns_when_a_paid_listing_is_still_a_draft(): void
    {
        SystemSetting::create(['key' => 'whatsapp_number', 'value' => '999111222']);
        $city = DestinationCity::where('name', 'Iquitos')->firstOrFail();
        $organization = Organization::create([
            'type' => 'marketplace_business',
            'legal_name' => 'Lodge Pagado SAC',
            'commercial_name' => 'Lodge Pagado',
            'ruc' => '20999111222',
            'email' => 'lodge-pagado@test.pe',
            'status' => 'active',
        ]);
        $plan = SubscriptionPlan::create([
            'code' => 'alerta-ficha',
            'name' => 'Plan alerta',
            'monthly_price' => 120,
            'currency' => 'PEN',
            'is_active' => true,
        ]);
        Subscription::create([
            'organization_id' => $organization->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'monthly_price' => 120,
            'currency' => 'PEN',
            'starts_on' => today(),
        ]);
        DestinationListing::create([
            'organization_id' => $organization->id,
            'destination_city_id' => $city->id,
            'type' => 'lodging',
            'status' => 'draft',
            'name' => 'Lodge Pagado',
            'is_active' => false,
        ]);

        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Lodge Pagado pagó el plan y su ficha sigue en borrador');
    }

    public function test_dashboard_ignores_a_draft_listing_without_an_active_plan(): void
    {
        SystemSetting::create(['key' => 'whatsapp_number', 'value' => '999111222']);
        $city = DestinationCity::where('name', 'Iquitos')->firstOrFail();
        $organization = Organization::create([
            'type' => 'marketplace_business',
            'legal_name' => 'Lodge Pendiente SAC',
            'commercial_name' => 'Lodge Pendiente',
            'ruc' => '20999111333',
            'email' => 'lodge-pendiente@test.pe',
            'status' => 'active',
        ]);
        DestinationListing::create([
            'organization_id' => $organization->id,
            'destination_city_id' => $city->id,
            'type' => 'lodging',
            'status' => 'draft',
            'name' => 'Lodge Pendiente',
            'is_active' => false,
        ]);

        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Lodge Pendiente pagó el plan');
    }

    public function test_company_fleet_counts_use_real_plurals(): void
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Flota Uno SAC',
            'commercial_name' => 'Flota Uno',
            'ruc' => '20111000111',
            'email' => 'flota@test.pe',
            'status' => 'active',
        ]);
        Vessel::create([
            'organization_id' => $organization->id,
            'name' => 'Rápida Uno',
            'registration_number' => 'DICAPI-UNO',
            'vessel_type' => 'rapido',
            'seat_capacity' => 20,
            'status' => 'draft',
        ]);

        $this->actingAs($this->superAdmin())->get(route('admin.companies.index', ['tab' => 'rendimiento']))
            ->assertOk()
            ->assertSee('1 registrada')
            ->assertSee('0 habilitadas')
            ->assertDontSee('registrada(s)');
    }

    public function test_public_air_ports_follow_modality_and_keep_legacy_airfields(): void
    {
        Port::create(['name' => 'Puerto Lupita', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'aereo', 'port_type' => 'privado', 'is_active' => true]);
        Port::create(['name' => 'Aeródromo Viejo', 'city' => 'Contamana', 'region' => 'Loreto', 'modality' => 'fluvial', 'port_type' => 'aeropuerto', 'is_active' => true]);
        Port::create(['name' => 'Embarcadero Nanay', 'city' => 'Iquitos', 'region' => 'Loreto', 'modality' => 'fluvial', 'port_type' => 'embarcadero', 'is_active' => true]);

        $this->get(route('ports.public', ['tipo' => 'aereo']))
            ->assertOk()
            ->assertSee('Puerto Lupita')
            ->assertSee('Aeródromo Viejo')
            ->assertDontSee('Embarcadero Nanay');

        $this->get(route('ports.public', ['tipo' => 'fluvial']))
            ->assertOk()
            ->assertSee('Embarcadero Nanay')
            ->assertDontSee('Puerto Lupita')
            ->assertDontSee('Aeródromo Viejo');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));

        return $admin;
    }
}
