<?php

namespace Tests\Feature;

use App\Models\BusinessConversation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_and_superadmin_can_exchange_messages_and_other_business_cannot_read_them(): void
    {
        $businessRole = Role::firstOrCreate(['code' => 'marketplace_admin'], ['name' => 'Anunciante']);
        $adminRole = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador']);
        $organization = Organization::create(['type' => 'marketplace_business', 'legal_name' => 'Lodge Uno SAC', 'commercial_name' => 'Lodge Uno', 'ruc' => '20123456789', 'status' => 'active']);
        $owner = User::factory()->create();
        $owner->organizations()->attach($organization->id, ['status' => 'active']);
        $owner->roles()->attach($businessRole->id, ['organization_id' => $organization->id]);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole->id);

        $this->actingAs($owner)->post(route('messages.store'), ['assigned_admin_id' => $admin->id, 'subject' => 'Consulta de portada', 'body' => '¿Cuándo se publica mi campaña?'])->assertRedirect();
        $conversation = BusinessConversation::firstOrFail();
        $this->assertSame($admin->id, $conversation->assigned_admin_id);
        $this->assertDatabaseHas('business_messages', ['business_conversation_id' => $conversation->id, 'sender_id' => $owner->id]);
        $this->actingAs($owner)->get(route('messages.index', ['conversation' => $conversation]))->assertOk()->assertSee('Plan y facturación')->assertSee('Administrador destinatario');
        $this->actingAs($admin)->get(route('messages.index', ['conversation' => $conversation]))->assertOk()->assertSee('Consulta de portada')->assertSee('20123456789')->assertSee('Empresa anunciante')->assertSee($owner->name)->assertSee($admin->name);
        $this->actingAs($admin)->post(route('messages.reply', $conversation), ['body' => 'La campaña está en revisión.'])->assertRedirect();
        $this->actingAs($owner)->get(route('messages.index', ['conversation' => $conversation]))->assertOk()->assertSee('La campaña está en revisión.');

        $otherOrganization = Organization::create(['type' => 'marketplace_business', 'legal_name' => 'Lodge Dos SAC', 'ruc' => '20987654321', 'status' => 'active']);
        $other = User::factory()->create();
        $other->organizations()->attach($otherOrganization->id, ['status' => 'active']);
        $other->roles()->attach($businessRole->id, ['organization_id' => $otherOrganization->id]);
        $this->actingAs($other)->post(route('messages.reply', $conversation), ['body' => 'No autorizado'])->assertForbidden();
    }
}
