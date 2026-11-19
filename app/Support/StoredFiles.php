<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFiles
{
    public static function uploadsDisk(): string
    {
        return (string) config('filesystems.uploads_disk', 'public');
    }

    public static function documentsDisk(): string
    {
        return (string) config('filesystems.documents_disk', 'local');
    }

    public static function storePublic(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::uploadsDisk());

        return self::publicUrl($path) ?? $path;
    }

    public static function storeAvatar(UploadedFile $file): string
    {
        return self::storePublic($file, 'avatars');
    }

    public static function putPublic(string $path, string $contents): void
    {
        Storage::disk(self::uploadsDisk())->put($path, $contents);
    }

    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk(self::uploadsDisk())->url($path);
    }

    public static function deletePublic(?string $stored): void
    {
        $relative = self::relativePublicPath($stored);
        if ($relative === null) {
            return;
        }
        Storage::disk(self::uploadsDisk())->delete($relative);
    }

    public static function storeDocument(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::documentsDisk());
    }

    public static function deleteDocument(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        Storage::disk(self::documentsDisk())->delete($path);
    }

    public static function documentDownload(string $path, ?string $name): StreamedResponse
    {
        return Storage::disk(self::documentsDisk())->download($path, $name ?: 'documento');
    }

    public static function documentResponse(string $path, ?string $name): StreamedResponse
    {
        return Storage::disk(self::documentsDisk())->response($path, $name ?: 'archivo');
    }

    public static function relativePublicPath(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        $base = rtrim((string) Storage::disk(self::uploadsDisk())->url(''), '/');
        if ($base !== '' && str_starts_with($stored, $base)) {
            return ltrim(substr($stored, strlen($base)), '/');
        }
        if (str_starts_with($stored, '/storage/')) {
            return ltrim(substr($stored, strlen('/storage/')), '/');
        }
        if (! str_contains($stored, '://') && ! str_starts_with($stored, '/')) {
            return $stored;
        }

        return null;
    }
}
