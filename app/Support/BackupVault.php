<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;

class BackupVault
{
    public const FRESH_HOURS = 26;

    public const DATABASE_PREFIX = 'respaldos/base';

    public const UPLOADS_PREFIX = 'respaldos/archivos';

    private const ERROR_FILE = 'respaldos/ultimo-error.txt';

    public function disk(): string
    {
        return 's3_private';
    }

    public function privateReady(): bool
    {
        $disk = config('filesystems.disks.s3_private');

        return is_array($disk)
            && filled($disk['bucket'] ?? null)
            && filled($disk['key'] ?? null)
            && filled($disk['secret'] ?? null);
    }

    public function missingMessage(): string
    {
        return 'No hay bucket privado de R2. El respaldo no se guardó.';
    }

    public function stamp(): string
    {
        return now()->timezone(DepartureSchedule::ZONE)->format('Ymd-His');
    }

    public function putFile(string $path, string $absolute): bool
    {
        $stream = fopen($absolute, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            return Storage::disk($this->disk())->writeStream($path, $stream) !== false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function prune(string $prefix): void
    {
        $disk = Storage::disk($this->disk());
        $threshold = now()->subDays((int) config('operations.backups.keep_days'))->getTimestamp();

        foreach ($disk->allFiles($prefix) as $file) {
            if ($disk->lastModified($file) < $threshold) {
                $disk->delete($file);
            }
        }
    }

    public function markFailure(string $message): void
    {
        if (! $this->privateReady()) {
            return;
        }

        Storage::disk($this->disk())->put(self::ERROR_FILE, now()->toIso8601String().' '.$message);
    }

    public function clearFailure(): void
    {
        if (! $this->privateReady()) {
            return;
        }

        Storage::disk($this->disk())->delete(self::ERROR_FILE);
    }

    public function latestDump(): ?string
    {
        if (! $this->privateReady() || ! $this->safeToRead()) {
            return null;
        }

        $disk = Storage::disk($this->disk());
        $latest = null;
        $latestTime = 0;
        foreach ($disk->files(self::DATABASE_PREFIX) as $file) {
            if (! str_ends_with($file, '.dump')) {
                continue;
            }
            $time = $disk->lastModified($file);
            if ($time >= $latestTime) {
                $latestTime = $time;
                $latest = $file;
            }
        }

        return $latest;
    }

    public function enabled(): bool
    {
        return (bool) config('operations.backups.enabled');
    }

    public function alert(): ?string
    {
        if (! $this->enabled()) {
            return 'Los respaldos automáticos están apagados. Configura R2 y activa BACKUPS_ENABLED en Railway.';
        }
        if (! $this->privateReady()) {
            return $this->missingMessage();
        }
        if (! $this->safeToRead()) {
            return 'No hay un respaldo de la base de las últimas 26 horas.';
        }

        $latest = $this->latestMoment();
        $error = $this->errorMoment();
        if ($error !== null && ($latest === null || $error->greaterThan($latest))) {
            return 'El último respaldo de la base falló.';
        }
        if ($latest === null || $latest->lt(now()->subHours(self::FRESH_HOURS))) {
            return 'No hay un respaldo de la base de las últimas 26 horas.';
        }

        return null;
    }

    public function uploadsAlreadyRemote(): bool
    {
        return $this->remoteDisk(StoredFiles::uploadsDisk()) && $this->remoteDisk(StoredFiles::documentsDisk());
    }

    private function safeToRead(): bool
    {
        if (! app()->runningUnitTests()) {
            return true;
        }

        try {
            return Storage::disk($this->disk())->getAdapter() instanceof LocalFilesystemAdapter;
        } catch (\Throwable) {
            return false;
        }
    }

    private function remoteDisk(string $name): bool
    {
        $disk = config('filesystems.disks.'.$name);

        return is_array($disk) && ($disk['driver'] ?? null) === 's3' && filled($disk['bucket'] ?? null);
    }

    private function latestMoment(): ?Carbon
    {
        $path = $this->latestDump();
        if ($path === null) {
            return null;
        }

        return Carbon::createFromTimestamp(Storage::disk($this->disk())->lastModified($path));
    }

    private function errorMoment(): ?Carbon
    {
        $disk = Storage::disk($this->disk());
        if (! $disk->exists(self::ERROR_FILE)) {
            return null;
        }

        return Carbon::createFromTimestamp($disk->lastModified(self::ERROR_FILE));
    }
}
