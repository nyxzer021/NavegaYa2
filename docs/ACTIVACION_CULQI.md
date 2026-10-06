# Activación de Culqi en NavegaYA

El sistema mantiene pagos de prueba mientras `CULQI_ENABLED=false`.

Cuando la empresa Culqi esté afiliada, configura estas variables en el archivo `.env` del servidor:

```dotenv
CULQI_ENABLED=true
CULQI_ENVIRONMENT=production
CULQI_PUBLIC_KEY=pk_live_...
CULQI_SECRET_KEY=sk_live_...
CULQI_WEBHOOK_SECRET=...
```

No subas `.env` al repositorio ni compartas la llave secreta. La llave pública se usa en el navegador para Culqi Checkout; la secreta y el secreto de webhook permanecen solo en Laravel. Después de modificar `.env`, ejecuta:

```powershell
php artisan config:clear
```

Antes de activar producción, se debe probar el Checkout con llaves `test` y registrar el webhook de Culqi. El webhook es quien confirma el cobro real; el navegador no debe confirmar pagos por sí solo.
