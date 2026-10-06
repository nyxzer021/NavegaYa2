# Sprint 5: pagos y puesta en producción

## Culqi queda pendiente de claves

La aplicación no incluye claves ni realiza cobros reales. Para preparar la integración, copia los valores en el archivo `.env` del servidor, nunca en Git:

```dotenv
PAYMENTS_SANDBOX=false
CULQI_ENABLED=true
CULQI_ENVIRONMENT=production
CULQI_PUBLIC_KEY=
CULQI_SECRET_KEY=
CULQI_WEBHOOK_SECRET=
CULQI_API_URL=https://api.culqi.com/v2
```

Mientras `PAYMENTS_SANDBOX=true`, el botón de pago confirma una reserva solamente para pruebas y el boleto indicará que no hubo cobro real. Al desactivarlo, ese endpoint devuelve 404 y deja de poder confirmar pagos simulados.

Antes de activar Cobros Culqi se debe implementar y validar el token de tarjeta en frontend, creación de cargo en servidor, webhook firmado y conciliación de estados. La confirmación de una reserva debe provenir del webhook de Culqi, nunca de un dato enviado por el navegador.

## Operación previa a producción

- Usar HTTPS y `APP_ENV=production`, `APP_DEBUG=false`.
- Configurar correo transaccional mediante las variables `MAIL_*` ya presentes en `.env.example`.
- Programar una copia diaria de PostgreSQL y probar regularmente su restauración.
- Ejecutar workers de cola y el programador de Laravel en el servidor.
- Mantener respaldos de archivos subidos y revisar `storage/logs` con alertas.
