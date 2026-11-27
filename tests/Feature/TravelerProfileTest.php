<?php

namespace Tests\Feature;

use App\Models\MasterRoute;
use App\Models\Organization;
use App\Models\Port;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_traveler_saves_phone_and_document_in_the_profile(): void
    {
        $traveler = User::factory()->create();

        $this->actingAs($traveler)->patch(route('profile.update'), [
            'name' => 'Rosa Viajera', 'email' => $traveler->email,
            'phone' => '965 111 222', 'document_type' => 'DNI', 'document_number' => '45678912',
        ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

        $traveler->refresh();
        $this->assertSame('965 111 222', $traveler->phone);
        $this->assertSame('DNI', $traveler->document_type);
        $this->assertSame('45678912', $traveler->document_number);
        $this->actingAs($traveler)->get(route('profile.edit'))->assertOk()->assertSee('45678912')->assertSee('965 111 222');
    }

    public function test_a_dni_must_have_eight_digits_and_a_document_needs_its_type(): void
    {
        $traveler = User::factory()->create();

        $this->actingAs($traveler)->patch(route('profile.update'), [
            'name' => 'Rosa', 'email' => $traveler->email, 'document_type' => 'DNI', 'document_number' => '123',
        ])->assertSessionHasErrors('document_number');

        $this->actingAs($traveler)->patch(route('profile.update'), [
            'name' => 'Rosa', 'email' => $traveler->email, 'document_number' => 'X1234567',
        ])->assertSessionHasErrors('document_type');

        $this->actingAs($traveler)->patch(route('profile.update'), [
            'name' => 'Rosa', 'email' => $traveler->email, 'document_type' => 'Pasaporte', 'document_number' => 'X1234567',
        ])->assertSessionHasNoErrors();
    }

    public function test_the_booking_form_is_already_filled_with_the_profile(): void
    {
        $traveler = User::factory()->create([
            'name' => 'Rosa Viajera', 'phone' => '965111222', 'document_type' => 'CE', 'document_number' => 'CE998877',
        ]);
        $departure = $this->departure();

        $this->actingAs($traveler)->get(route('bookings.create', $departure))
            ->assertOk()
            ->assertSee('value="CE998877"', false)
            ->assertSee('value="965111222"', false)
            ->assertSee('value="Rosa Viajera"', false)
            ->assertSee('value="CE" selected', false);

        $this->get(route('bookings.create', $departure))->assertOk();
    }

    public function test_the_first_purchase_fills_an_empty_profile_without_overwriting_saved_data(): void
    {
        $empty = User::factory()->create(['phone' => null, 'document_number' => null]);
        $empty->rememberBuyerDetails(['contact_phone' => '965000111', 'contact_document' => '41111111']);
        $this->assertSame('965000111', $empty->fresh()->phone);
        $this->assertSame('41111111', $empty->fresh()->document_number);

        $saved = User::factory()->create(['phone' => '999888777', 'document_number' => '42222222']);
        $saved->rememberBuyerDetails(['contact_phone' => '965000111', 'contact_document' => '41111111']);
        $this->assertSame('999888777', $saved->fresh()->phone);
        $this->assertSame('42222222', $saved->fresh()->document_number);
    }

    private function departure(): RouteDeparture
    {
        $organization = Organization::create([
            'type' => 'transport_company', 'legal_name' => 'Perfil Río SAC', 'ruc' => '20699555666', 'status' => 'active',
        ]);
        $origin = Port::create(['name' => 'Puerto Perfil', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Destino Perfil', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::create([
            'organization_id' => $organization->id, 'name' => 'Lancha Perfil', 'registration_number' => 'MAT-PERFIL',
            'vessel_type' => 'rapido', 'seat_capacity' => 10, 'status' => 'ready',
        ]);
        $vessel->seats()->create(['code' => '1A', 'row_position' => 1, 'column_position' => 1, 'deck' => 'principal', 'space_type' => 'seat', 'is_available' => true]);
        $master = MasterRoute::create([
            'code' => 'PERFIL-1', 'modality' => 'fluvial', 'origin_city' => 'Iquitos', 'destination_city' => 'Nauta',
            'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active',
        ]);
        $route = TransportRoute::create([
            'organization_id' => $organization->id, 'master_route_id' => $master->id,
            'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id,
        ]);

        return RouteDeparture::create([
            'transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDays(3),
            'fare' => 80, 'status' => 'scheduled', 'is_published' => true,
        ]);
    }
}
