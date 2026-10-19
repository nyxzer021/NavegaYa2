# Publicación de NavegaYA

## Lo que ya queda preparado en el proyecto

- Plantilla segura: `.env.production.example`.
- Verificación previa: `php artisan navegaya:production-readiness`.
- Script de actualización: `deploy/deploy-production.sh`.
- Cabeceras de seguridad, sesiones seguras, cola, tareas programadas y respaldos configurables.

## Antes de ejecutar el despliegue

1. Contrata o prepara un servidor Linux con PHP 8.4, PostgreSQL, Composer, Nginx o Apache y `pg_dump`.
2. Apunta el dominio al servidor e instala un certificado HTTPS.
3. Copia `.env.production.example` como `.env` y completa únicamente las claves y datos del servidor.
4. Configura el correo transaccional. Sin él no llegarán los enlaces de verificación o recuperación de contraseña.
5. Configura un cron cada minuto para `php artisan schedule:run` y un servicio para `php artisan queue:work database --sleep=3 --tries=3`.
6. Copia `storage/app/backups` cada día a un servicio externo seguro.
7. Ejecuta el verificador. Debe terminar con éxito antes de abrir el sitio al público.

```powershell
& 'C:\Users\dreyna\.config\herd\bin\php84\php.exe' artisan test
& 'C:\Users\dreyna\.config\herd\bin\php84\php.exe' artisan migrate:status
& 'C:\Users\dreyna\.config\herd\bin\php84\php.exe' artisan navegaya:production-readiness
```

El verificador bloquea la publicación si detecta SQLite, pagos simulados, correo de prueba,
almacenamiento local, respaldos desactivados, sesiones inseguras o credenciales incompletas
de Culqi cuando la pasarela está habilitada.

Después del despliegue realiza una prueba autenticada por cada perfil: viajero, administrador
de transportista, counter, anunciante y superadministrador. La prueba mínima debe cubrir una
reserva y pago real de importe controlado, emisión del boleto, embarque, historial del viajero,
campaña publicitaria y revisión administrativa.

## Pagos e inicio con Google

Puedes publicar sin Culqi ni Google: ambos permanecerán desactivados. Actívalos únicamente después de registrar el dominio HTTPS y las credenciales en sus respectivos portales.
