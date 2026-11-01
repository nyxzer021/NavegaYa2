<?php

return [
    'backups' => [
        // En producción activar solo después de definir BACKUP_PG_DUMP_PATH.
        'enabled' => (bool) env('BACKUPS_ENABLED', false),
        'pg_dump_path' => env('BACKUP_PG_DUMP_PATH', 'pg_dump'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    ],

    'monitoring' => [
        'max_failed_jobs' => (int) env('OPERATIONS_MAX_FAILED_JOBS', 0),
    ],

    // Las lanchas a menudo zarpan una o dos horas después de la hora citada.
    // La tarea revisa cada 15 minutos, pero solo cierra cuando ya pasó este margen.
    'departure_close_grace_hours' => (int) env('DEPARTURE_CLOSE_GRACE_HOURS', 3),
];
