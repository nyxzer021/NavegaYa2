<section class="space-y-6">
    <header>
        <h2 class="text-lg font-black text-slate-900">Eliminar cuenta</h2>
        <p class="mt-1 text-sm leading-6 text-slate-600">
            Se cierra tu acceso y se borra el perfil. Las reservas y los boletos se conservan para el viaje y el manifiesto de la empresa, pero dejan de estar vinculados a esta cuenta. Esta acción pide tu contraseña y no se puede deshacer.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Eliminar cuenta</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-black text-slate-900">¿Eliminar tu cuenta de viajero?</h2>

            <p class="mt-1 text-sm leading-6 text-slate-600">
                Tus reservas y boletos siguen existiendo para el operador. Escribe tu contraseña para confirmar que quieres perder el acceso a esta cuenta.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Contraseña" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="Contraseña"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Cancelar
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    Eliminar cuenta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
