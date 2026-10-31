<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SiteHero;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicHeroManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_manage_public_page_heroes(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'admin'], ['name' => 'Administrador']));
        $admin->permissions()->attach(Permission::firstOrCreate(['code' => 'settings'], ['name' => 'Configuración general']));

        $this->actingAs($admin)->get(route('admin.site-heroes.index'))->assertForbidden();

        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));

        $this->actingAs($superAdmin)->get(route('admin.site-heroes.index'))
            ->assertOk()
            ->assertSee('Portadas de páginas públicas');
    }

    public function test_super_admin_can_upload_a_page_hero(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));

        $this->actingAs($superAdmin)->patch(route('admin.site-heroes.update', 'companies'), [
            'image' => UploadedFile::fake()->image('empresas.jpg', 1600, 700),
        ])->assertRedirect();

        $hero = SiteHero::where('key', 'companies')->firstOrFail();
        Storage::disk('public')->assertExists($hero->image_path);
    }

    public function test_public_hero_has_one_consistent_responsive_height(): void
    {
        $this->get(route('companies.index'))
            ->assertOk()
            ->assertSee('height: 240px !important', false)
            ->assertSee('height: 340px !important', false);
    }
}
