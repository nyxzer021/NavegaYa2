<x-guest-layout title="Recuperar contraseña · NavegaYA" description="Recupera el acceso a tu cuenta de NavegaYA para consultar reservas, descargar boletos y seguir tus viajes por Loreto.">
    <div class="mb-4 text-sm text-gray-600">
        Escribe el correo de tu cuenta y te enviaremos un enlace para elegir una contraseña nueva.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Enviar enlace de recuperación
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
