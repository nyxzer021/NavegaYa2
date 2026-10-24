<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_and_only_see_their_requests(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $customer->roles()->attach(Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente']));
        $this->actingAs($customer)->post(route('support.store'), [
            'category' => 'payment', 'priority' => 'high', 'subject' => 'Pago no reflejado',
            'description' => 'El pago fue confirmado pero no aparece.',
        ])->assertRedirect();
        SupportTicket::create(['user_id' => $other->id, 'code' => 'SOL-OTHER', 'requester_type' => 'customer', 'category' => 'other', 'priority' => 'normal', 'subject' => 'Solicitud privada', 'description' => 'No debe verla otro usuario.']);

        $this->actingAs($customer)->get(route('support.index'))->assertOk()->assertSee('Pago no reflejado')->assertDontSee('Solicitud privada');
        $this->assertDatabaseHas('support_tickets', ['user_id' => $customer->id, 'requester_type' => 'customer']);
    }

    public function test_super_admin_can_respond_and_close_a_request(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Administrador']));
        $ticket = SupportTicket::create(['user_id' => User::factory()->create()->id, 'code' => 'SOL-TEST', 'requester_type' => 'customer', 'category' => 'technical', 'priority' => 'normal', 'subject' => 'Error técnico', 'description' => 'Detalle.']);

        $this->actingAs($admin)->patch(route('admin.support.respond', $ticket), ['status' => 'resolved', 'admin_response' => 'La incidencia fue corregida.'])->assertRedirect();
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'resolved', 'responded_by' => $admin->id]);
    }

    public function test_regular_user_cannot_respond_to_requests(): void
    {
        $ticket = SupportTicket::create(['user_id' => User::factory()->create()->id, 'code' => 'SOL-DENIED', 'requester_type' => 'customer', 'category' => 'account', 'priority' => 'normal', 'subject' => 'Cuenta', 'description' => 'Detalle.']);
        $this->actingAs(User::factory()->create())->patch(route('admin.support.respond', $ticket), ['status' => 'resolved', 'admin_response' => 'Intento no autorizado.'])->assertForbidden();
    }
}
