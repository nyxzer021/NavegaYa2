<x-public-layout title="Mi cuenta · NavegaYA" description="Actualiza el nombre, el correo y la contraseña de tu cuenta de viajero en NavegaYA, y revisa qué ocurre con tus reservas si eliminas el acceso.">
    <div class="bg-slate-50 px-4 py-10 sm:px-6">
        <div class="mx-auto max-w-3xl space-y-6">
            <header>
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">Cuenta del viajero</p>
                <h1 class="mt-1 text-3xl font-black text-emerald-950">Mi cuenta</h1>
                <p class="mt-2 text-sm text-slate-600">Estos datos identifican tus reservas y boletos. No cambian el diseño del sitio público.</p>
            </header>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @include('profile.partials.update-profile-information-form')
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @include('profile.partials.update-password-form')
            </section>

            <section class="rounded-3xl border border-rose-200 bg-white p-6 shadow-sm sm:p-8">
                @include('profile.partials.delete-user-form')
            </section>
        </div>
    </div>
</x-public-layout>
