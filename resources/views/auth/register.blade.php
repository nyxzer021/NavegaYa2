<x-guest-layout>
    <section class="auth-heading">
        <p>CUENTA DE VIAJERO</p>
        <h1>Viaja con NavegaYA</h1>
        <span>Consulta tus reservas y comparte tu experiencia después del viaje.</span>
    </section>
    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf
        <div class="auth-grid">
            <div class="auth-field auth-full"><x-input-label for="name" value="Nombres y apellidos" /><x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
            <div class="auth-field"><x-input-label for="email" value="Correo electrónico" /><x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="email" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
            <div class="auth-field"><x-input-label for="phone" value="Teléfono o WhatsApp" /><x-text-input id="phone" class="block mt-1 w-full" type="tel" name="phone" :value="old('phone')" required autocomplete="tel" placeholder="999 999 999" /><x-input-error :messages="$errors->get('phone')" class="mt-2" /></div>
            <div class="auth-field auth-full"><x-input-label for="document_number" value="DNI o documento (opcional)" /><x-text-input id="document_number" class="block mt-1 w-full" type="text" name="document_number" :value="old('document_number')" /><x-input-error :messages="$errors->get('document_number')" class="mt-2" /></div>
            <div class="auth-field"><x-input-label for="password" value="Contraseña" /><x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
            <div class="auth-field"><x-input-label for="password_confirmation" value="Confirmar contraseña" /><x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" /></div>
        </div>
        <p class="auth-note">Tus datos se usan para administrar tus reservas. El documento se valida nuevamente al emitir cada boleto.</p>
        <x-primary-button class="auth-submit">Crear mi cuenta</x-primary-button>
        <p class="auth-switch">¿Ya tienes una cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
    </form>
    <div class="oauth-separator"><span>o regístrate con</span></div>
    @if(config('services.google.enabled'))
        <a class="google-button" href="{{ route('google.redirect') }}"><b>G</b> Continuar con Google</a>
    @else
        <span class="google-button is-pending" title="Pendiente de configuración de Google"><b>G</b> Continuar con Google <small>Próximamente</small></span>
    @endif
</x-guest-layout>
