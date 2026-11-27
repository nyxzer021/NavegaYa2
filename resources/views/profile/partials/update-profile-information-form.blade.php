<section>
    <header>
        <h2 class="text-lg font-black text-slate-900">Datos de tu cuenta</h2>
        <p class="mt-1 text-sm text-slate-600">Actualiza el nombre y el correo con los que consultas tus viajes.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Nombre" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-sm text-slate-800">
                        Verifica tu correo para vincular compras hechas sin cuenta y publicar valoraciones.

                        <button form="send-verification" class="rounded-md text-sm text-emerald-800 underline">
                            Reenviar correo de verificación
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-emerald-700">Te enviamos un nuevo enlace de verificación.</p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="phone" value="WhatsApp o teléfono" />
            <x-text-input id="phone" name="phone" type="tel" inputmode="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" placeholder="965 111 222" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[180px_1fr]">
            <div>
                <x-input-label for="document_type" value="Tipo de documento" />
                <select id="document_type" name="document_type" class="mt-1 block min-h-11 w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                    <option value="">Elige</option>
                    @foreach(['DNI' => 'DNI', 'CE' => 'Carné de extranjería', 'Pasaporte' => 'Pasaporte'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('document_type', $user->document_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('document_type')" />
            </div>
            <div>
                <x-input-label for="document_number" value="Número de documento" />
                <x-text-input id="document_number" name="document_number" type="text" class="mt-1 block w-full" :value="old('document_number', $user->document_number)" maxlength="20" />
                <x-input-error class="mt-2" :messages="$errors->get('document_number')" />
            </div>
        </div>
        <p class="text-sm text-slate-600">Con estos datos, tu nombre, documento y WhatsApp aparecen ya llenos cuando compras un pasaje.</p>

        <div class="flex items-center gap-4">
            <x-primary-button>Guardar</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600"
                >Cambios guardados.</p>
            @endif
        </div>
    </form>
</section>
