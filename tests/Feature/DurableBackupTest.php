<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DurableBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_local_upload_backup_goes_to_the_private_bucket_and_old_copies_are_removed(): void
    {
        $this->privateDisk();
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('s3');
        Storage::disk('public')->put('company-profiles/logo.jpg', 'logo');
        Storage::disk('s3_private')->put('respaldos/archivos/archivos-viejo.zip', 'viejo');
        touch(Storage::disk('s3_private')->path('respaldos/archivos/archivos-viejo.zip'), now()->subDays(30)->getTimestamp());

        $this->artisan('navegaya:backup-uploads')->assertSuccessful();

        $stored = Storage::disk('s3_private')->allFiles('respaldos/archivos');
        $this->assertCount(1, $stored);
        $this->assertStringStartsWith('respaldos/archivos/archivos-', $stored[0]);
        $this->assertStringEndsWith('.zip', $stored[0]);
        $this->assertSame([], Storage::disk('s3')->allFiles());
        Storage::disk('public')->assertExists('company-profiles/logo.jpg');
    }

    public function test_backups_fail_with_a_warning_when_private_r2_is_missing(): void
    {
        config([
            'operations.backups.enabled' => true,
            'filesystems.disks.s3_private.bucket' => null,
            'filesystems.disks.s3_private.key' => null,
            'filesystems.disks.s3_private.secret' => null,
        ]);

        $this->artisan('navegaya:backup-database', ['--force' => true])
            ->expectsOutputToContain('No hay bucket privado de R2')
            ->assertFailed();
        $this->artisan('navegaya:backup-uploads')
            ->expectsOutputToContain('No hay bucket privado de R2')
            ->assertFailed();
        $this->artisan('navegaya:backup-verify')
            ->expectsOutputToContain('No hay bucket privado de R2')
            ->assertFailed();
    }

    public function test_uploads_already_on_r2_are_not_copied(): void
    {
        config([
            'operations.backups.enabled' => true,
            'filesystems.uploads_disk' => 's3',
            'filesystems.documents_disk' => 's3_private',
            'filesystems.disks.s3.driver' => 's3',
            'filesystems.disks.s3.bucket' => 'publico',
            'filesystems.disks.s3_private.driver' => 's3',
            'filesystems.disks.s3_private.bucket' => 'privado',
            'filesystems.disks.s3_private.key' => 'llave',
            'filesystems.disks.s3_private.secret' => 'secreto',
        ]);
        Storage::fake('s3');
        Storage::fake('s3_private');

        $this->artisan('navegaya:backup-uploads')
            ->expectsOutputToContain('ya están en R2')
            ->assertSuccessful();

        $this->assertSame([], Storage::disk('s3_private')->allFiles());
        $this->assertSame([], Storage::disk('s3')->allFiles());
    }

    public function test_operations_health_alerts_when_the_database_backup_is_older_than_26_hours(): void
    {
        $this->privateDisk();
        Storage::disk('s3_private')->put('respaldos/base/navegaya-viejo.dump', 'dump');
        touch(Storage::disk('s3_private')->path('respaldos/base/navegaya-viejo.dump'), now()->subHours(27)->getTimestamp());

        $this->artisan('navegaya:operations-health')
            ->expectsOutputToContain('últimas 26 horas')
            ->assertFailed();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No hay un respaldo de la base de las últimas 26 horas.');

        touch(Storage::disk('s3_private')->path('respaldos/base/navegaya-viejo.dump'), now()->getTimestamp());

        $this->artisan('navegaya:operations-health')->assertSuccessful();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('No hay un respaldo de la base de las últimas 26 horas.');
    }

    public function test_with_backups_off_the_health_check_stays_quiet_and_the_panel_reminds_it(): void
    {
        config(['operations.backups.enabled' => false]);

        $this->artisan('navegaya:operations-health')->assertSuccessful();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Los respaldos automáticos están apagados.');
    }

    public function test_verify_refuses_to_restore_when_there_is_no_dump(): void
    {
        $this->privateDisk();

        $this->artisan('navegaya:backup-verify')
            ->expectsOutputToContain('No hay un respaldo de la base para comprobar.')
            ->assertFailed();
    }

    private function privateDisk(): void
    {
        config([
            'operations.backups.enabled' => true,
            'filesystems.uploads_disk' => 'public',
            'filesystems.documents_disk' => 'local',
            'filesystems.disks.s3_private.bucket' => 'privado',
            'filesystems.disks.s3_private.key' => 'llave',
            'filesystems.disks.s3_private.secret' => 'secreto',
        ]);
        Storage::fake('s3_private');
    }
}
