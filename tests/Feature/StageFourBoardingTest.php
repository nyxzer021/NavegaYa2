<?php

namespace Tests\Feature;

use App\Models\CargoShipment;
use App\Models\CrewMember;
use App\Models\Organization;
use App\Models\Port;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselSeat;
use App\Support\CrewRoster;
use App\Support\ManifestReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageFourBoardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_boarding_marks_a_passenger_by_document_and_builds_both_manifests(): void
    {
        [$admin, $organization, $departure, $adult, $infant] = $this->departure();
        $this->actingAs($admin)->get(route('company.boarding.show', $departure))
            ->assertOk()
            ->assertSee('Rosa Demo')
            ->assertSee('Marcar a bordo')
            ->assertSee('No se presentó')
            ->assertDontSee('No hay boletos emitidos');

        $this->actingAs($admin)->get(route('company.boarding.show', ['departure' => $departure, 'q' => '44556677']))
            ->assertOk()
            ->assertSee('Rosa Demo')
            ->assertDontSee('Bebé Demo');

        $this->actingAs($admin)->post(route('company.boarding.seat', [$departure, $adult]), ['action' => 'board'])
            ->assertRedirect();
        $this->assertDatabaseHas('tickets', ['reservation_seat_id' => $adult->id, 'status' => 'boarded']);

        $token = $adult->fresh()->ticket->boarding_token;
        $this->actingAs($admin)->get(route('company.boarding.show', ['departure' => $departure, 'q' => $token]))
            ->assertOk()
            ->assertSee('Abordó');

        $this->actingAs($admin)->post(route('company.boarding.seat', [$departure, $infant]), ['action' => 'no_show'])
            ->assertRedirect();
        $this->assertDatabaseHas('tickets', ['reservation_seat_id' => $infant->id, 'status' => 'no_show']);

        $html = view('company.manifests.passengers-pdf', ManifestReport::river($departure->fresh()))->render();
        $this->assertStringContainsString('Rosa Demo', $html);
        $this->assertStringContainsString('En brazos', $html);
        $this->assertStringContainsString('Capitán Demo', $html);
        $this->assertStringContainsString('Faltan chalecos', $html);
        $cargo = view('company.manifests.cargo-pdf', [
            'departure' => $departure,
            'shipments' => ManifestReport::cargo($departure),
        ])->render();
        $this->assertStringContainsString('GUIA-DEMO', $cargo);
        $this->assertStringContainsString('Remitente Demo', $cargo);

        $this->actingAs($admin)->get(route('company.boarding.passengers', $departure))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin)->get(route('company.boarding.cargo', $departure))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_superadmin_can_download_manifests_and_cannot_board(): void
    {
        [, , $departure] = $this->departure();
        $super = User::factory()->create();
        $super->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadmin'])->id);

        $this->actingAs($super)->get(route('admin.manifests.show', $departure))
            ->assertOk()
            ->assertSee('PDF de pasajeros')
            ->assertDontSee('Marcar a bordo');
        $this->actingAs($super)->get(route('admin.manifests.passengers', $departure))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($super)->get(route('admin.boarding.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Organization, 2: RouteDeparture, 3: ReservationSeat, 4: ReservationSeat}
     */
    private function departure(): array
    {
        $organization = Organization::create([
            'type' => 'transport_company',
            'legal_name' => 'Embarque SAC',
            'ruc' => '20999000105',
            'email' => 'embarque@test.pe',
            'status' => 'active',
            'modality' => 'fluvial',
        ]);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto', 'is_active' => true]);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto', 'is_active' => true]);
        $vessel = Vessel::create([
            'organization_id' => $organization->id,
            'name' => 'Lancha Manifiesto',
            'registration_number' => 'MAT-MANIF',
            'vessel_type' => 'lancha',
            'seat_capacity' => 24,
            'life_vest_count' => 1,
            'status' => 'ready',
        ]);
        $seat = VesselSeat::create(['vessel_id' => $vessel->id, 'code' => '1A', 'deck' => 'principal', 'seat_class' => 'standard', 'row_position' => 1, 'column_position' => 1]);
        $route = TransportRoute::create([
            'organization_id' => $organization->id,
            'origin_port_id' => $origin->id,
            'destination_port_id' => $destination->id,
            'estimated_duration_minutes' => 90,
            'code' => 'RUT-MANIF',
        ]);
        $departure = RouteDeparture::create([
            'transport_route_id' => $route->id,
            'vessel_id' => $vessel->id,
            'departure_at' => now()->addDay(),
            'fare' => 80,
            'status' => 'scheduled',
        ]);
        $captain = CrewMember::create([
            'organization_id' => $organization->id,
            'name' => 'Capitán Demo',
            'document_number' => '70000022',
            'role' => 'captain',
            'license_number' => 'LIC-DEMO',
            'license_expires_on' => now()->addYear()->toDateString(),
            'status' => 'active',
        ]);
        CrewRoster::sync($departure, $captain->id, []);
        $reservation = Reservation::create([
            'route_departure_id' => $departure->id,
            'code' => 'NY-MANIF',
            'contact_name' => 'Rosa Demo',
            'contact_email' => 'rosa@test.pe',
            'contact_phone' => '999',
            'total_amount' => 80,
            'status' => 'confirmed',
        ]);
        $adult = ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'vessel_seat_id' => $seat->id,
            'passenger_name' => 'Rosa Demo',
            'document_number' => '44556677',
            'passenger_age' => 34,
            'passenger_type' => 'adult',
        ]);
        $infant = ReservationSeat::create([
            'reservation_id' => $reservation->id,
            'route_departure_id' => $departure->id,
            'passenger_name' => 'Bebé Demo',
            'document_number' => '00000001',
            'passenger_type' => 'infant',
        ]);
        CargoShipment::create([
            'route_departure_id' => $departure->id,
            'code' => 'GUIA-DEMO',
            'sender_name' => 'Remitente Demo',
            'sender_phone' => '999',
            'recipient_name' => 'Destino',
            'description' => 'Caja',
            'package_count' => 1,
            'declared_weight_kg' => 12,
        ]);

        return [$admin, $organization, $departure, $adult, $infant];
    }
}
