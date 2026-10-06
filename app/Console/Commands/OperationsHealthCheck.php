<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperationsHealthCheck extends Command
{
    protected $signature = 'navegaya:operations-health';

    protected $description = 'Revisa trabajos fallidos de la cola y los registra en el log';

    public function handle(): int
    {
        $failedJobs = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        $maximum = config('operations.monitoring.max_failed_jobs');

        if ($failedJobs > $maximum) {
            Log::critical('Se detectaron trabajos fallidos en la cola.', compact('failedJobs', 'maximum'));
            $this->components->error("Hay {$failedJobs} trabajo(s) fallido(s) en la cola.");

            return self::FAILURE;
        }

        Log::info('Verificación operativa correcta.', compact('failedJobs'));
        $this->components->info('Colas y trabajos fallidos verificados.');

        return self::SUCCESS;
    }
}
