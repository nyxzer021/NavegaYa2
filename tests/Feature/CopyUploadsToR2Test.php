<?php

namespace Tests\Feature;

use App\Support\StoredFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CopyUploadsToR2Test extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_stay_on_the_public_disk_when_cloud_is_not_configured(): void
    {
        $this->assertSame('public', config('filesystems.uploads_disk'));
        $this->assertSame('local', config('filesystems.documents_disk'));

        Storage::fake('public');
        $url = StoredFiles::storePublic(UploadedFile::fake()->image('logo.jpg'), 'company-profiles');

        $this->assertStringContainsString('/storage/company-profiles/', (string) $url);
        Storage::disk('public')->assertExists((string) StoredFiles::relativePublicPath($url));
    }

    public function test_the_copy_can_run_twice_without_removing_the_originals(): void
    {
        config([
            'filesystems.disks.s3.bucket' => 'prueba',
            'filesystems.disks.s3.key' => 'prueba',
            'filesystems.disks.s3.secret' => 'prueba',
            'filesystems.disks.s3_private.bucket' => 'privado',
        ]);
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('s3');
        Storage::fake('s3_private');
        Storage::disk('public')->put('company-profiles/logo.jpg', 'logo');
        Storage::disk('local')->put('organization-documents/1/ruc.pdf', 'pdf');

        $this->artisan('navegaya:copy-uploads-to-r2')
            ->expectsOutputToContain('Copiados: 2. Ya estaban: 0. Fallidos: 0.')
            ->assertSuccessful();

        Storage::disk('s3')->assertExists('company-profiles/logo.jpg');
        Storage::disk('s3_private')->assertExists('organization-documents/1/ruc.pdf');
        Storage::disk('s3')->assertMissing('organization-documents/1/ruc.pdf');
        Storage::disk('public')->assertExists('company-profiles/logo.jpg');
        Storage::disk('local')->assertExists('organization-documents/1/ruc.pdf');

        $this->artisan('navegaya:copy-uploads-to-r2')
            ->expectsOutputToContain('Copiados: 0. Ya estaban: 2. Fallidos: 0.')
            ->assertSuccessful();
    }

    public function test_documents_are_never_copied_to_the_public_bucket_without_a_private_one(): void
    {
        config([
            'filesystems.disks.s3.bucket' => 'prueba',
            'filesystems.disks.s3.key' => 'prueba',
            'filesystems.disks.s3.secret' => 'prueba',
            'filesystems.disks.s3_private.bucket' => null,
        ]);
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('s3');
        Storage::disk('public')->put('company-profiles/logo.jpg', 'logo');
        Storage::disk('local')->put('organization-documents/1/ruc.pdf', 'pdf');

        $this->artisan('navegaya:copy-uploads-to-r2')
            ->expectsOutputToContain('Copiados: 1. Ya estaban: 0. Fallidos: 0.')
            ->assertSuccessful();

        Storage::disk('s3')->assertExists('company-profiles/logo.jpg');
        Storage::disk('s3')->assertMissing('organization-documents/1/ruc.pdf');
    }

    public function test_images_saved_before_r2_point_to_the_bucket_once_uploads_move(): void
    {
        config(['filesystems.uploads_disk' => 's3', 'filesystems.disks.s3.url' => 'https://archivos.example.test']);

        $this->assertSame('https://archivos.example.test/company-profiles/logo.jpg', StoredFiles::publicUrl('/storage/company-profiles/logo.jpg'));
        $this->assertSame('/images/hero.jpg', StoredFiles::publicUrl('/images/hero.jpg'));
    }
}
