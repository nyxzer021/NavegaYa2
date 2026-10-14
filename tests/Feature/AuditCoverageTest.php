<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrative_creations_updates_and_deletions_are_audited_without_secrets(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']));
        $this->actingAs($admin);
        $ad = Advertisement::create(['business_name' => 'Auditoría Lodge', 'business_type' => 'lodge_hotel', 'contact_name' => 'Responsable', 'phone_whatsapp' => '999999999', 'city_destination' => 'Iquitos', 'placements' => ['home_hero'], 'category' => 'Lodge / hotel', 'title' => 'Campaña auditable', 'status' => 'lead_pending']);
        $ad->update(['status' => 'active']);
        $ad->delete();
        $logs = AuditLog::where('subject_type', Advertisement::class)->where('subject_id', $ad->id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'deleted'], $logs->pluck('event')->all());
        $this->assertSame($admin->id, $logs[1]->user_id);
        $this->assertSame('lead_pending', $logs[1]->changes['old']['status']);
        $this->assertSame('active', $logs[1]->changes['new']['status']);
        $this->assertArrayNotHasKey('password', $logs[0]->changes['new']);

        $this->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Historial completo de operaciones')
            ->assertSee('Campaña auditable');
    }
}
