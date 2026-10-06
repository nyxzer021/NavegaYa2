<x-guest-layout>
    <section class="auth-heading">
        <p>SEGURIDAD DE CUENTA</p>
        <h1>Confirma tu correo</h1>
        <span>Te enviamos un enlace para confirmar que el correo te pertenece.</span>
    </section>
    <div class="auth-form">
        <p class="auth-note">Revisa tu bandeja de entrada y también la carpeta de correo no deseado. La verificación se solicita para publicar valoraciones y proteger tu cuenta de viajero.</p>
        @if (session('status') === 'verification-link-sent')
            <p class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">Se envió un nuevo enlace de verificación a tu correo electrónico.</p>
        @endif
        <div class="auth-links" style="align-items:center">
            <form method="POST" action="{{ route('verification.send') }}">@csrf<x-primary-button class="auth-submit">Reenviar enlace</x-primary-button></form>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="text-sm font-bold text-[#087669]">Cerrar sesión</button></form>
        </div>
    </div>
</x-guest-layout>
