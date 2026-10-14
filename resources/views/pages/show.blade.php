<x-public-layout :title="$page->title.' · NavegaYA'" :description="$page->summary ?: 'Información legal de NavegaYA'">
    <main class="bg-slate-50 px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <nav class="mb-5 text-xs text-slate-500" aria-label="Ruta de navegación"><a href="{{route('home')}}" class="font-semibold text-emerald-800 hover:underline">Inicio</a><span class="mx-2">/</span><span>Información legal</span></nav>
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-200 px-6 py-7 sm:px-10 sm:py-9">
                    <p class="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Información legal de NavegaYA</p>
                    <h1 class="mt-2 text-2xl font-extrabold leading-tight text-slate-950 sm:text-3xl">{{$page->title}}</h1>
                    @if($page->summary)<p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">{{$page->summary}}</p>@endif
                </header>
                <div class="px-6 py-7 sm:px-10 sm:py-10">
                    <div class="whitespace-pre-line text-[13px] leading-7 text-slate-600 sm:text-sm">{{$page->content}}</div>
                </div>
            </article>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-xs"><a href="{{route('home')}}" class="font-bold text-emerald-800 hover:underline">← Volver a NavegaYA</a><span class="text-slate-400">Documento publicado por NavegaYA</span></div>
        </div>
    </main>
</x-public-layout>
