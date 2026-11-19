<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'navegaya:backup-database {--stdout : Escribe el respaldo en la salida estándar, para guardarlo fuera del contenedor} {--force : Lo ejecuta aunque los respaldos automáticos estén apagados}';

    protected $description = 'Crea una copia PostgreSQL en storage/app/backups/database o en la salida estándar';

    public function handle(): int
    {
        $stdout = (bool) $this->option('stdout');
        if (! $stdout && ! $this->option('force') && ! config('operations.backups.enabled')) {
            $this->components->info('Respaldos automáticos desactivados.');

            return self::SUCCESS;
        }

        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->components->error('El respaldo automático requiere una conexión PostgreSQL.');

            return self::FAILURE;
        }

        $directory = storage_path('app/backups/database');
        $filename = 'navegaya-'.now()->format('Ymd-His').'.dump';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        if (! $stdout) {
            File::ensureDirectoryExists($directory);
        }

        $command = [
            config('operations.backups.pg_dump_path'),
            '--format=custom',
            '--no-owner',
            '--no-acl',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? 5432),
            '--username='.(string) ($connection['username'] ?? ''),
            (string) $connection['database'],
        ];
        if (! $stdout) {
            $command[] = '--file='.$path;
        }
        $process = new Process($command);
        $process->setTimeout(600);
        $process->setEnv(['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
        $process->run($stdout ? function (string $type, string $buffer): void {
            if ($type === Process::OUT) {
                $this->output->write($buffer);
            }
        } : null);

        if (! $process->isSuccessful()) {
            if (! $stdout) {
                File::delete($path);
            }
            Log::critical('Falló el respaldo PostgreSQL.', ['exit_code' => $process->getExitCode(), 'error' => $process->getErrorOutput()]);
            $this->components->error('No se pudo crear el respaldo de PostgreSQL.');

            return self::FAILURE;
        }

        if ($stdout) {
            return self::SUCCESS;
        }

        $this->deleteExpired($directory);
        Log::info('Respaldo PostgreSQL creado.', ['file' => $filename]);
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
