<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use App\Support\StoredFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupUploads extends Command
{
    protected $signature = 'navegaya:backup-uploads';

    protected $description = 'Copia los archivos locales al bucket privado, en respaldos/archivos';

    public function handle(BackupVault $vault): int
    {
        if (! config('operations.backups.enabled')) {
            $this->components->info('Respaldos automáticos desactivados.');

            return self::SUCCESS;
        }

        if ($vault->uploadsAlreadyRemote()) {
            $this->components->info('Las imágenes y los documentos ya están en R2. No hace falta copiarlos.');

            return self::SUCCESS;
        }

        if (! $vault->privateReady()) {
            Log::critical($vault->missingMessage());
            $this->components->error($vault->missingMessage());

            return self::FAILURE;
        }

        if (! class_exists(ZipArchive::class)) {
            Log::critical('No está habilitada la extensión ZIP para respaldar archivos.');
            $this->components->error('Habilita la extensión ZIP de PHP para respaldar archivos.');

            return self::FAILURE;
        }

        $temp = tempnam(sys_get_temp_dir(), 'nyfiles');
        if ($temp === false) {
            $this->components->error('No se pudo preparar el archivo temporal del respaldo.');

            return self::FAILURE;
        }

        $zip = new ZipArchive;
        if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::delete($temp);
            Log::critical('No se pudo abrir el archivo ZIP de respaldo.');
            $this->components->error('No se pudo abrir el archivo ZIP de respaldo.');

            return self::FAILURE;
        }

        $added = $this->addDisk($zip, StoredFiles::uploadsDisk(), 'publico');
        $added += $this->addDisk($zip, StoredFiles::documentsDisk(), 'privado');
        $zip->close();

        if ($added === 0) {
            File::delete($temp);
            $this->components->info('No hay archivos locales para copiar.');

            return self::SUCCESS;
        }

        $path = BackupVault::UPLOADS_PREFIX.'/archivos-'.$vault->stamp().'.zip';
        $stored = $vault->putFile($path, $temp);
        File::delete($temp);
        if (! $stored) {
            Log::critical('No se pudo subir el respaldo de archivos al bucket privado.');
            $this->components->error('No se pudo subir el respaldo de archivos al bucket privado.');

            return self::FAILURE;
        }

        $vault->prune(BackupVault::UPLOADS_PREFIX);
        Log::info('Respaldo de archivos guardado en el bucket privado.', ['file' => $path]);
        $this->components->info('Respaldo guardado en el bucket privado: '.$path);

        return self::SUCCESS;
    }

    private function addDisk(ZipArchive $zip, string $disk, string $folder): int
    {
        $config = config('filesystems.disks.'.$disk);
        if (! is_array($config) || ($config['driver'] ?? null) === 's3') {
            return 0;
        }

        $added = 0;
        foreach (Storage::disk($disk)->allFiles() as $path) {
            if (str_starts_with($path, 'respaldos/')) {
                continue;
            }
            $zip->addFile(Storage::disk($disk)->path($path), $folder.'/'.$path);
            $added++;
        }

        return $added;
    }
}
