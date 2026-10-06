# Inicio de sesión con Google

El botón aparece en `/login` y `/register` solo cuando `GOOGLE_LOGIN_ENABLED=true`.

1. En Google Cloud Console crea un cliente OAuth 2.0 de tipo **Aplicación web**.
2. Añade como URI de redirección autorizada `https://TU-DOMINIO/auth/google/callback`.
3. En el `.env` del servidor configura:

```dotenv
GOOGLE_LOGIN_ENABLED=true
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="https://TU-DOMINIO/auth/google/callback"
```

4. Ejecuta `php artisan optimize:clear`.

Las claves quedan solo en `.env`; no deben subirse a Git. Google crea o vincula una cuenta de pasajero por correo electrónico y no concede permisos administrativos.
