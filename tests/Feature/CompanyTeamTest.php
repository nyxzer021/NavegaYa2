<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashSession;
use App\Models\Organization;
use App\Models\OrganizationSite;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotice;
use App\Support\CompanyTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompanyTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invitation_creates_the_employee_with_their_profile_when_accepted(): void
    {
        Notification::fake();
        [$admin, $organization] = $this->company();

        $this->actingAs($admin)->post(route('company.staff.store'), [
            'name' => 'Rosa Operadora',
            'email' => 'rosa.operadora@test.pe',
            'profile' => 'operator',
            'password' => 'no-la-escribe-el-admin',
        ])->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'rosa.operadora@test.pe']);
        $invitation = StaffInvitation::query()->firstOrFail();
        $this->assertSame('operator', $invitation->profile);
        $this->assertTrue($invitation->expires_at->greaterThan(now()->addDays(6)));
        Notification::assertSentOnDemand(StaffInvitationNotice::class);
        $this->actingAs($admin)->get(route('company.staff.index'))
            ->assertOk()
            ->assertSee('Rosa Operadora')
            ->assertSee(route('staff.invitation', $invitation->token), false)
            ->assertSee('Enviar por WhatsApp')
            ->assertDontSee('name="password"', false);

        $this->post(route('staff.invitation.store', $invitation->token), [
            'password' => 'clave-propia-1',
            'password_confirmation' => 'clave-propia-1',
        ])->assertRedirect(route('company.departures.index'));

        $operator = User::query()->where('email', 'rosa.operadora@test.pe')->firstOrFail();
        $this->assertNotNull($operator->last_login_at);
        $this->assertTrue(Hash::check('clave-propia-1', $operator->password));
        $this->assertFalse(Hash::check('no-la-escribe-el-admin', $operator->password));
        $this->assertSame('operator', CompanyTeam::memberProfile($operator, $organization->id));
        $this->actingAs($operator)->get(route('company.departures.index'))->assertOk()->assertDontSee('Punto de venta')->assertDontSee('Cierre de caja');
        $this->actingAs($operator)->get(route('company.pos.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.sales.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('company.cash-close.index'))->assertForbidden();
        $log = AuditLog::query()->where('event', 'staff.invited')->firstOrFail();
        $this->assertSame($organization->id, $log->organization_id);
        $this->assertArrayNotHasKey('password', $log->changes);
    }

    public function test_an_existing_passenger_is_linked_without_changing_the_password(): void
    {
        [$admin, $organization] = $this->company();
        $passenger = User::factory()->create([
            'email' => 'pasajero.equipo@test.pe',
            'password' => 'clave-de-pasajero',
        ]);
        $passenger->roles()->attach(Role::firstOrCreate(['code' => 'customer'], ['name' => 'Cliente'])->id);

        $this->actingAs($admin)->post(route('company.staff.store'), [
            'name' => 'Pasajero Equipo',
            'email' => 'pasajero.equipo@test.pe',
            'profile' => 'finance',
        ])->assertRedirect();
        $invitation = StaffInvitation::query()->firstOrFail();

        $this->get(route('staff.invitation', $invitation->token))
            ->assertOk()
            ->assertDontSee('name="password"', false);
        $this->post(route('staff.invitation.store', $invitation->token))
            ->assertRedirect(route('company.sales.index'));

        $passenger->refresh();
        $this->assertTrue(Hash::check('clave-de-pasajero', $passenger->password));
        $this->assertSame('finance', CompanyTeam::memberProfile($passenger, $organization->id));
        $this->actingAs($passenger)->post(route('company.cash-close.open'), ['opening_float' => 10])->assertForbidden();
    }

    public function test_an_expired_or_used_link_does_not_work(): void
    {
        [$admin] = $this->company();
        $this->actingAs($admin)->post(route('company.staff.store'), [
            'name' => 'Enlace Viejo',
            'email' => 'enlace.viejo@test.pe',
            'profile' => 'seller',
        ]);
        $invitation = StaffInvitation::query()->firstOrFail();
        $invitation->update(['expires_at' => now()->subMinute()]);

        $this->get(route('staff.invitation', $invitation->token))->assertStatus(410);
        $this->post(route('staff.invitation.store', $invitation->token), [
            'password' => 'clave-propia-1',
            'password_confirmation' => 'clave-propia-1',
        ])->assertStatus(410);
        $this->assertDatabaseMissing('users', ['email' => 'enlace.viejo@test.pe']);

        $invitation->update(['expires_at' => now()->addDay(), 'accepted_at' => now()]);
        $this->get(route('staff.invitation', $invitation->token))->assertStatus(410);
    }

    public function test_the_company_cannot_be_left_without_an_active_administrator(): void
    {
        [$admin, $organization] = $this->company();

        $this->actingAs($admin)->patch(route('company.staff.update', $admin), [
            'profile' => 'operator',
        ])->assertSessionHasErrors('profile');
        $this->actingAs($admin)->patch(route('company.staff.toggle', $admin))->assertSessionHasErrors('status');
        $this->assertSame('admin', CompanyTeam::memberProfile($admin->fresh(), $organization->id));
        $this->assertSame('active', $admin->organizations()->first()->pivot->status);
        $this->assertSame(1, CompanyTeam::activeAdmins($organization->id));
    }

    public function test_a_suspended_employee_cannot_enter_and_loses_open_sessions(): void
    {
        [$admin, $organization] = $this->company();
        $operator = $this->member($organization, 'company_operator', 'Nilo Operador');
        DB::table('sessions')->insert([
            'id' => 'sesion-de-nilo',
            'user_id' => $operator->id,
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)->patch(route('company.staff.toggle', $operator))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-de-nilo']);
        $this->assertSame('suspended', $operator->organizations()->first()->pivot->status);
        $this->actingAs($operator)->get(route('company.departures.index'))->assertForbidden();
        auth()->logout();
        $this->post(route('login'), ['email' => $operator->email, 'password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspending_someone_with_an_open_drawer_asks_for_confirmation_and_keeps_the_drawer(): void
    {
        [$admin, $organization] = $this->company();
        $seller = $this->member($organization, 'company_counter', 'Caja Abierta');
        $session = CashSession::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $seller->id,
            'opened_at' => now(),
            'opening_float' => 40,
        ]);

        $this->actingAs($admin)->patch(route('company.staff.toggle', $seller))
            ->assertRedirect()
            ->assertSessionHasErrors('confirm');
        $this->assertSame('active', $seller->organizations()->first()->pivot->status);
        $this->assertNull($session->fresh()->closed_at);

        $this->actingAs($admin)->patch(route('company.staff.toggle', $seller), ['confirm' => 1])->assertRedirect();
        $this->assertSame('suspended', $seller->organizations()->first()->pivot->status);
        $this->assertNull($session->fresh()->closed_at);
    }

    public function test_a_company_cannot_invite_or_change_another_companys_people(): void
    {
        [$admin, $organization] = $this->company();
        $other = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => 'Ajena Equipo SAC', 'ruc' => '20999999111',
            'email' => 'ajena-equipo@test.pe', 'status' => 'active',
        ]);
        $foreign = $this->member($other, 'company_operator', 'Ajeno Equipo');
        $foreignInvite = StaffInvitation::query()->create([
            'organization_id' => $other->id,
            'name' => 'Invitado Ajeno',
            'email' => 'invitado.ajeno@test.pe',
            'profile' => 'seller',
            'token' => str_repeat('a', 40),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)->patch(route('company.staff.update', $foreign), ['profile' => 'finance'])->assertNotFound();
        $this->actingAs($admin)->patch(route('company.staff.toggle', $foreign))->assertNotFound();
        $this->actingAs($admin)->post(route('company.staff.invitations.resend', $foreignInvite))->assertNotFound();
        $this->assertSame('operator', CompanyTeam::memberProfile($foreign->fresh(), $other->id));
        $this->assertNotSame($organization->id, $other->id);
    }

    public function test_a_seller_needs_a_branch_when_the_company_has_one(): void
    {
        [$admin, $organization] = $this->company();
        OrganizationSite::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Muelle Equipo',
            'kind' => 'dock',
            'address' => 'Malecón 1',
            'city' => 'Iquitos',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('company.staff.store'), [
            'name' => 'Sin Sede',
            'email' => 'sin.sede@test.pe',
            'profile' => 'seller',
        ])->assertSessionHasErrors('site_id');
        $this->assertDatabaseCount('staff_invitations', 0);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function company(): array
    {
        $organization = Organization::query()->create([
            'type' => 'transport_company', 'legal_name' => 'Equipo SAC', 'commercial_name' => 'Equipo',
            'ruc' => '20999999088', 'email' => 'equipo@test.pe', 'status' => 'active',
        ]);
        $admin = User::factory()->create(['email' => 'admin.equipo@test.pe']);
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador'])->id, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }

    private function member(Organization $organization, string $code, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['code' => $code], ['name' => $name])->id, ['organization_id' => $organization->id]);
        $user->organizations()->attach($organization->id, ['status' => 'active']);

        return $user;
    }
}
