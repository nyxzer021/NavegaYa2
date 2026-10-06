# Operación del servidor

Estas tareas no dependen de Culqi. Protegen los datos, archivos y procesos de NavegaYA.

## Respaldos

El proyecto incorpora dos comandos:

```bash
php artisan navegaya:backup-database
php artisan navegaya:backup-uploads
```

Ambos están desactivados por defecto. En el archivo `.env` de producción define:

```dotenv
BACKUPS_ENABLED=true
BACKUP_PG_DUMP_PATH=/usr/bin/pg_dump
BACKUP_KEEP_DAYS=14
```

Se ejecutan cada día a las 02:00 y 02:20. Generan copias en `storage/app/backups/database` y `storage/app/backups/uploads`. El servidor debe copiar esa carpeta a almacenamiento externo seguro; conservar una copia en el mismo servidor no protege ante una pérdida total del equipo.

Para restaurar una base de datos PostgreSQL creada en formato custom:

```bash
pg_restore --clean --if-exists --host=HOST --username=USUARIO --dbname=BASE storage/app/backups/database/ARCHIVO.dump
```

Prueba una restauración antes de habilitar el sistema para clientes.

## Programador y cola

El programador revisa la operación cada cinco minutos, poda trabajos fallidos antiguos y ejecuta los respaldos cuando estén habilitados.

En Linux, el cron del usuario del sitio debe incluir:

```cron
* * * * * cd /var/www/navegaya && php artisan schedule:run >> /dev/null 2>&1
```

El worker de cola debe ejecutarse como servicio supervisado:

```bash
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
```

En Windows Server, crea dos tareas en el Programador de tareas: una cada minuto para `php artisan schedule:run` y otra permanente para `php artisan queue:work database --sleep=3 --tries=3`.

## Monitoreo

`php artisan navegaya:operations-health` revisa la tabla de trabajos fallidos. Con el valor predeterminado `OPERATIONS_MAX_FAILED_JOBS=0`, cualquier error queda como registro crítico en `storage/logs/laravel.log` y el comando falla para que el supervisor o la plataforma de monitoreo genere una alerta.

En producción usa `LOG_CHANNEL=stack`, `LOG_STACK=daily` y `LOG_LEVEL=warning`; luego conecta esos logs al monitoreo que elijas.
