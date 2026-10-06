# Prueba de NavegaYA en GitHub y Railway

## 1. Repositorio GitHub

El proyecto ya queda inicializado como repositorio Git local. Crea un repositorio vacío en GitHub llamado `navegaya` y conecta su URL:

```powershell
& 'C:\Program Files\Git\cmd\git.exe' remote add origin https://github.com/TU_USUARIO/navegaya.git
& 'C:\Program Files\Git\cmd\git.exe' push -u origin main
```

No subas `.env`, claves Culqi, Google OAuth ni contraseñas SMTP. El `.gitignore` ya los excluye.

## 2. Railway

1. Crea un proyecto en Railway y selecciona **Deploy from GitHub Repo**.
2. Selecciona el repositorio `navegaya`.
3. Añade el servicio **PostgreSQL** dentro del mismo proyecto.
4. En el servicio web, copia los valores de `.railway.env.example` a **Variables**. Railway resolverá las referencias `${{Postgres.*}}`.
5. Genera `APP_KEY` desde la terminal local:

```powershell
& 'C:\Users\dreyna\.config\herd\bin\php84\php.exe' artisan key:generate --show
```

6. Cuando Railway asigne el dominio, actualiza `APP_URL` y vuelve a desplegar.
7. Comprueba `https://TU-DOMINIO/up`. Debe responder correctamente antes de probar rutas, reservas, pago sandbox y PDFs.

## Servicios adicionales antes de producción

- Para correos reales, configura SMTP/Resend y cambia `MAIL_MAILER`.
- Para archivos de empresas y destinos, usa almacenamiento S3/Cloudinary; el disco local de Railway es efímero.
- Para colas y tareas programadas, crea un Worker separado con `php artisan queue:work` y un Cron/servicio Scheduler con `php artisan schedule:work`.
- Mantén Culqi y Google OAuth desactivados durante las pruebas hasta registrar sus credenciales y dominios de retorno.
