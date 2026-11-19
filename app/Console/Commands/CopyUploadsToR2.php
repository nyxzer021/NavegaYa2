<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CopyUploadsToR2 extends Command
{
    protected $signature = 'navegaya:copy-uploads-to-r2';

    protected $description = 'Copia a R2 las imágenes (bucket público) y los documentos (bucket privado), sin borrar los originales';

    public function handle(): int
    {
        $disk = config('filesystems.disks.s3');
        if (! is_array($disk) || blank($disk['bucket'] ?? null) || blank($disk['key'] ?? null) || blank($disk['secret'] ?? null)) {
            $this->components->error('Faltan AWS_BUCKET, AWS_ACCESS_KEY_ID o AWS_SECRET_ACCESS_KEY. No se copió nada.');

            return self::FAILURE;
        }

        $targets = ['public' => 's3'];
        if (filled(config('filesystems.disks.s3_private.bucket'))) {
            $targets['local'] = 's3_private';
        } else {
            $this->components->warn('Falta AWS_PRIVATE_BUCKET: los documentos no se copiaron. Nunca van al bucket público.');
        }

        $copied = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($targets as $source => $destination) {
            [$copiedNow, $skippedNow, $failedNow] = $this->copyDisk($source, $destination);
            $copied += $copiedNow;
            $skipped += $skippedNow;
            $failed += $failedNow;
        }

        $this->components->info("Copiados: {$copied}. Ya estaban: {$skipped}. Fallidos: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function copyDisk(string $source, string $destination): array
    {
        $copied = 0;
        $skipped = 0;
        $failed = 0;
        $from = Storage::disk($source);
        $to = Storage::disk($destination);

        foreach ($from->allFiles() as $path) {
            $sourceSize = $from->size($path);
            if ($to->exists($path) && $to->size($path) === $sourceSize) {
                $skipped++;

                continue;
            }

            $stream = $from->readStream($path);
            if (! is_resource($stream)) {
                $failed++;

                continue;
            }

            try {
                $stored = $to->writeStream($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if ($stored === false) {
                $failed++;

                continue;
            }

            $copied++;
        }

        return [$copied, $skipped, $failed];
    }
}
