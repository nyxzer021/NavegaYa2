<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VesselSeatLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_a_vessel_seat_layout(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['code' => 'super_admin', 'name' => 'Super administrador']);
        $admin->roles()->attach($role);
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Fluvial Test SAC', 'ruc' => '20123456789', 'email' => 'empresa@test.pe', 'status' => 'active']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Rio Test', 'registration_number' => 'PA-0001', 'vessel_type' => 'lancha', 'seat_capacity' => 24]);

        $this->actingAs($admin)->post(route('admin.vessels.seats.generate', $vessel), [
            'rows' => 5, 'left_seats' => 2, 'right_seats' => 2, 'deck' => 'principal', 'seat_class' => 'standard',
        ])->assertRedirect(route('admin.vessels.seats.edit', ['vessel' => $vessel, 'deck' => 'principal']));

        $this->assertDatabaseCount('vessel_seats', 20);
        $this->assertDatabaseHas('vessel_seats', ['vessel_id' => $vessel->id, 'code' => '1A']);
        $this->assertDatabaseHas('vessel_seats', ['vessel_id' => $vessel->id, 'code' => '5D']);
        $this->actingAs($admin)->post(route('admin.vessels.seats.generate', $vessel), [
            'rows' => 2, 'left_seats' => 1, 'right_seats' => 1, 'deck' => 'superior', 'seat_class' => 'premium',
        ])->assertRedirect(route('admin.vessels.seats.edit', ['vessel' => $vessel, 'deck' => 'superior']));
        $this->assertDatabaseCount('vessel_seats', 24);
        $this->assertDatabaseHas('vessel_seats', ['vessel_id' => $vessel->id, 'deck' => 'superior', 'code' => '1A']);
        $this->actingAs($admin)->get(route('admin.vessels.seats.edit', ['vessel' => $vessel, 'deck' => 'principal']))
            ->assertOk()
            ->assertViewHas('otherDeckSeats', 4);
    }

    public function test_layout_cannot_exceed_vessel_capacity(): void
    {
        $admin = User::factory()->create();
        $role = Role::create(['code' => 'super_admin', 'name' => 'Super administrador']);
        $admin->roles()->attach($role);
        $company = Organization::create(['type' => 'transport_company', 'legal_name' => 'Fluvial Test SAC', 'ruc' => '20123456789', 'email' => 'empresa@test.pe', 'status' => 'active']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Rio Test', 'registration_number' => 'PA-0001', 'vessel_type' => 'lancha', 'seat_capacity' => 10]);

        $this->actingAs($admin)->from(route('admin.vessels.seats.edit', $vessel))->post(route('admin.vessels.seats.generate', $vessel), [
            'rows' => 3, 'left_seats' => 2, 'right_seats' => 2, 'deck' => 'principal', 'seat_class' => 'standard',
        ])->assertRedirect(route('admin.vessels.seats.edit', $vessel))->assertSessionHasErrors('rows');

        $this->assertDatabaseCount('vessel_seats', 0);
    }
}
