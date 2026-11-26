<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class BackupVerify extends Command
{
    protected $signature = 'navegaya:backup-verify';

    protected $description = 'Descarga el último respaldo de la base y comprueba que pg_restore lo lee, sin restaurarlo';

    public function handle(BackupVault $vault): int
    {
        if (! $vault->privateReady()) {
            $this->components->error($vault->missingMessage());

            return self::FAILURE;
        }

        $remote = $vault->latestDump();
        if ($remote === null) {
            $this->components->error('No hay un respaldo de la base para comprobar.');

            return self::FAILURE;
        }

        $temp = tempnam(sys_get_temp_dir(), 'nyverify');
        if ($temp === false) {
            $this->components->error('No se pudo preparar el archivo temporal.');

            return self::FAILURE;
        }

        $stream = Storage::disk($vault->disk())->readStream($remote);
        if (! is_resource($stream)) {
            File::delete($temp);
            $this->components->error('No se pudo descargar el respaldo.');

            return self::FAILURE;
        }

        $local = fopen($temp, 'wb');
        if ($local === false) {
            fclose($stream);
            File::delete($temp);
            $this->components->error('No se pudo preparar el archivo temporal.');

            return self::FAILURE;
        }
        stream_copy_to_stream($stream, $local);
        fclose($stream);
        fclose($local);

        $process = new Process([
            (string) config('operations.backups.pg_restore_path'),
            '--list',
            $temp,
        ]);
        $process->setTimeout(120);
        $process->run();
        File::delete($temp);

        if (! $process->isSuccessful()) {
            $this->components->error('pg_restore no pudo leer el respaldo. No se restauró nada.');

            return self::FAILURE;
        }

        $this->components->info('El respaldo se lee. No se restauró nada.');

        return self::SUCCESS;
    }
}
