<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperationsHealthCheck extends Command
{
    protected $signature = 'navegaya:operations-health';

    protected $description = 'Revisa la cola y que exista un respaldo de la base de las últimas 26 horas';

    public function handle(BackupVault $vault): int
    {
        $failed = false;
        $failedJobs = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        $maximum = config('operations.monitoring.max_failed_jobs');

        if ($failedJobs > $maximum) {
            Log::critical('Se detectaron trabajos fallidos en la cola.', compact('failedJobs', 'maximum'));
            $this->components->error("Hay {$failedJobs} trabajo(s) fallido(s) en la cola.");
            $failed = true;
        }

        // Con los respaldos apagados no se alerta cada 5 minutos: el aviso queda en el panel del superadmin.
        $backupAlert = $vault->enabled() ? $vault->alert() : null;
        if ($backupAlert !== null) {
            Log::critical($backupAlert);
            $this->components->error($backupAlert);
            $failed = true;
        }

        if ($failed) {
            return self::FAILURE;
        }

        Log::info('Verificación operativa correcta.', compact('failedJobs'));
        $this->components->info('Colas y respaldos verificados.');

        return self::SUCCESS;
    }
}
