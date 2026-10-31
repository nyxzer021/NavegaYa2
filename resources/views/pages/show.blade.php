<x-public-layout :title="$page->title.' · NavegaYA'" :description="$page->summary ?: \Illuminate\Support\Str::limit(strip_tags($page->content), 155)">
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
                    @php
                        $contentLines = collect(preg_split('/\R/', (string) $page->content));
                        $contentHeadings = $contentLines->filter(fn ($line) => preg_match('/^\s*(?:\d+[.)]|[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]{4,}:?)\s+/', trim($line)))->values();
                    @endphp
                    @if($contentHeadings->count()>3)<nav class="mb-8 rounded-2xl border border-emerald-100 bg-emerald-50 p-5" aria-label="Índice del documento"><strong class="text-sm text-emerald-950">Contenido</strong><ol class="mt-3 grid gap-2 text-xs sm:grid-cols-2">@foreach($contentHeadings as $heading)<li><a class="font-semibold text-emerald-800 hover:underline" href="#seccion-{{$loop->iteration}}">{{$heading}}</a></li>@endforeach</ol></nav>@endif
                    <div class="mx-auto max-w-[70ch] text-[13px] leading-7 text-slate-600 sm:text-sm">@php($headingIndex=0)@foreach($contentLines as $line)@if($contentHeadings->contains($line))@php($headingIndex++)<h2 id="seccion-{{$headingIndex}}" class="mb-3 mt-8 scroll-mt-28 text-xl font-extrabold leading-tight text-slate-950 first:mt-0">{{$line}}</h2>@elseif(trim($line)!=='')<p class="mb-4">{{$line}}</p>@endif @endforeach</div>
                </div>
            </article>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-xs"><a href="{{route('home')}}" class="font-bold text-emerald-800 hover:underline">← Volver a NavegaYA</a><span class="text-slate-400">Documento publicado por NavegaYA</span></div>
        </div>
    </main>
</x-public-layout>
