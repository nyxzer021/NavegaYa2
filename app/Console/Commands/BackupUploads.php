<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BackupUploads extends Command
{
    protected $signature = 'navegaya:backup-uploads';

    protected $description = 'Comprime los archivos cargados en storage/app/backups/uploads';

    public function handle(): int
    {
        if (! config('operations.backups.enabled')) {
            $this->components->info('Respaldos automáticos desactivados.');

            return self::SUCCESS;
        }

        if (! class_exists(ZipArchive::class)) {
            Log::critical('No está habilitada la extensión ZIP para respaldar archivos.');
            $this->components->error('Habilita la extensión ZIP de PHP para respaldar archivos.');

            return self::FAILURE;
        }

        $source = storage_path('app/public');
        $directory = storage_path('app/backups/uploads');
        File::ensureDirectoryExists($source);
        File::ensureDirectoryExists($directory);
        $filename = 'archivos-'.now()->format('Ymd-His').'.zip';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Log::critical('No se pudo abrir el archivo ZIP de respaldo.');

            return self::FAILURE;
        }

        foreach (File::allFiles($source) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();

        $this->deleteExpired($directory);
        Log::info('Respaldo de archivos creado.', ['file' => $filename]);
        $this->components->info("Respaldo creado: {$filename}");

        return self::SUCCESS;
    }

    private function deleteExpired(string $directory): void
    {
        $threshold = now()->subDays(config('operations.backups.keep_days'));

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() < $threshold->getTimestamp()) {
                File::delete($file->getPathname());
            }
        }
    }
}
