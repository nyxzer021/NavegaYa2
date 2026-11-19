<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneEphemeral extends Command
{
    protected $signature = 'navegaya:prune-ephemeral';

    protected $description = 'Borra sesiones vencidas y entradas caducadas de la caché';

    public function handle(): int
    {
        $now = now()->getTimestamp();
        $sessionCutoff = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();
        $sessions = $this->deleteOlderThan('sessions', 'last_activity', $sessionCutoff);
        $cache = $this->deleteOlderThan('cache', 'expiration', $now);
        $locks = $this->deleteOlderThan('cache_locks', 'expiration', $now);
        $this->components->info("Sesiones borradas: {$sessions}. Caché: {$cache}. Bloqueos: {$locks}.");

        return self::SUCCESS;
    }

    private function deleteOlderThan(string $table, string $column, int $cutoff): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->where($column, '<', $cutoff)->delete();
    }
}
