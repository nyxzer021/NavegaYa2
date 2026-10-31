<x-public-layout :title="$code.' · '.$title.' · NavegaYA'" :description="$message">
    <main class="grid min-h-[58vh] place-items-center bg-slate-50 px-4 py-16">
        <section class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm sm:p-12">
            <span class="text-6xl font-black text-amber-400">{{$code}}</span>
            <h1 class="mt-4 text-3xl font-black text-emerald-950">{{$title}}</h1>
            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-slate-600">{{$message}}</p>
            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row"><a href="{{route('home')}}" class="ny-button">Volver al inicio</a><a href="{{route('routes.index')}}" class="ny-button ny-button--accent">Buscar pasajes</a></div>
        </section>
    </main>
</x-public-layout>

