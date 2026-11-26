<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'navegaya:backup-database {--stdout : Escribe el respaldo en la salida estándar, para guardarlo fuera del contenedor} {--force : Lo ejecuta aunque los respaldos automáticos estén apagados}';

    protected $description = 'Crea una copia PostgreSQL y la guarda en el bucket privado, en respaldos/base';

    public function handle(BackupVault $vault): int
    {
        $stdout = (bool) $this->option('stdout');
        if (! $stdout && ! $this->option('force') && ! config('operations.backups.enabled')) {
            $this->components->info('Respaldos automáticos desactivados.');

            return self::SUCCESS;
        }

        if (! $stdout && ! $vault->privateReady()) {
            Log::critical($vault->missingMessage());
            $this->components->error($vault->missingMessage());

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->components->error('El respaldo automático requiere una conexión PostgreSQL.');

            return self::FAILURE;
        }

        $temp = tempnam(sys_get_temp_dir(), 'nydump');
        if ($temp === false) {
            $this->components->error('No se pudo preparar el archivo temporal del respaldo.');

            return self::FAILURE;
        }

        $command = [
            config('operations.backups.pg_dump_path'),
            '--format=custom',
            '--no-owner',
            '--no-acl',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 5432),
            '--username='.(string) ($connection['username'] ?? ''),
            '--file='.$temp,
            (string) $connection['database'],
        ];
        $process = new Process($command);
        $process->setTimeout(600);
        $process->setEnv(['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
        $process->run();

        if (! $process->isSuccessful()) {
            File::delete($temp);
            $vault->markFailure('No se pudo crear el respaldo de PostgreSQL.');
            Log::critical('Falló el respaldo PostgreSQL.', ['exit_code' => $process->getExitCode()]);
            $this->components->error('No se pudo crear el respaldo de PostgreSQL.');

            return self::FAILURE;
        }

        if ($stdout) {
            // OUTPUT_RAW: el respaldo es binario y la consola no debe interpretar ni cambiar sus bytes.
            $this->output->write((string) File::get($temp), false, OutputInterface::OUTPUT_RAW);
            File::delete($temp);

            return self::SUCCESS;
        }

        $path = BackupVault::DATABASE_PREFIX.'/navegaya-'.$vault->stamp().'.dump';
        $stored = $vault->putFile($path, $temp);
        File::delete($temp);
        if (! $stored) {
            $vault->markFailure('No se pudo subir el respaldo al bucket privado.');
            Log::critical('No se pudo subir el respaldo PostgreSQL al bucket privado.');
            $this->components->error('No se pudo subir el respaldo al bucket privado.');

            return self::FAILURE;
        }

        $vault->prune(BackupVault::DATABASE_PREFIX);
        $vault->clearFailure();
        Log::info('Respaldo PostgreSQL guardado en el bucket privado.', ['file' => $path]);
        $this->components->info('Respaldo guardado en el bucket privado: '.$path);

        return self::SUCCESS;
    }
}
