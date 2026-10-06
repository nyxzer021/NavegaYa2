<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_only_opens_assigned_modules(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $this->actingAs($user)->get(route('admin.ports.index'))->assertForbidden();

        $user->permissions()->attach(Permission::where('code', 'ports')->firstOrFail());
        $this->actingAs($user)->get(route('admin.ports.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.transport-routes.index'))->assertForbidden();
    }
}
