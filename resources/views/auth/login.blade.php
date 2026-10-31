<x-guest-layout>
    <section class="auth-heading">
        <p>CUENTA DE VIAJERO</p>
        <h1>Bienvenido de vuelta</h1>
        <span>Ingresa para consultar tus reservas, boletos y viajes.</span>
    </section>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf
        <div class="auth-grid auth-grid-single">
            <div class="auth-field"><x-input-label for="email" value="Correo electrónico" /><x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="email" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
            <div class="auth-field"><x-input-label for="password" value="Contraseña" /><x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
        </div>
        <label class="auth-remember"><input id="remember_me" type="checkbox" name="remember"> Mantener mi sesión iniciada</label>
        <x-primary-button class="auth-submit">Iniciar sesión</x-primary-button>
        <div class="auth-links">
            <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            <span>¿Aún no tienes cuenta? <a href="{{ route('register') }}">Regístrate</a></span>
        </div>
    </form>
    <div class="oauth-separator"><span>o continúa con</span></div>
    @if(config('services.google.enabled'))
        <a class="google-button" href="{{ route('google.redirect') }}"><b>G</b> Continuar con Google</a>
    @else
        <span class="google-button is-pending" title="Pendiente de configuración de Google"><b>G</b> Continuar con Google <small>Próximamente</small></span>
    @endif
</x-guest-layout>
