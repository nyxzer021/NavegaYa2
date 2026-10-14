<x-public-layout current="about" title="Quiénes somos · NavegaYA" description="Conoce el propósito de NavegaYA y cómo conectamos viajeros con empresas de transporte de la Amazonía peruana.">
    <main class="bg-slate-50 px-4 pb-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <x-amazon-hero-bg theme="home" class="text-center shadow-xl" overlay="from-slate-950/80 via-[#062c21]/65 to-emerald-950/35">
                <div class="mx-auto max-w-3xl px-4 pb-16 pt-10 sm:py-20">
                    <span class="inline-flex rounded-full border border-emerald-300/30 bg-emerald-950/60 px-3 py-1 text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-200">NavegaYA · Amazonía peruana</span>
                    <h1 class="mt-4 text-3xl font-extrabold leading-tight text-white sm:text-5xl">Conectamos viajes, ríos y personas</h1>
                    <p class="mx-auto mt-4 max-w-2xl text-sm leading-7 text-emerald-50/85 sm:text-base">Reunimos rutas fluviales y aéreas, empresas verificadas y destinos de Loreto para que cada viaje pueda planificarse con información clara y confiable.</p>
                </div>
            </x-amazon-hero-bg>

            <section class="mx-auto -mt-6 grid max-w-6xl gap-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-lg sm:p-8 lg:grid-cols-[.8fr_1.2fr] lg:p-10">
                <div><p class="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-700">Conoce NavegaYA</p><h2 class="mt-2 text-2xl font-extrabold leading-tight text-slate-950 sm:text-3xl">Información clara para viajar mejor</h2></div>
                <p class="text-sm leading-7 text-slate-600">NavegaYA es una plataforma digital creada para acercar a viajeros y empresas de transporte de la Amazonía. Integra rutas, salidas, embarcaciones, aeronaves, pasajes y destinos en un solo lugar, facilitando una experiencia más ordenada antes, durante y después del viaje.</p>
            </section>

            <section class="mx-auto mt-8 grid max-w-6xl gap-5 md:grid-cols-3">
                @foreach([
                    ['◎','Nuestro propósito','Facilitar el acceso a información confiable para buscar, comparar y reservar viajes por río y aire.'],
                    ['✓','Para los viajeros','Ofrecer horarios, empresas verificadas, pasajes digitales y orientación útil en una sola plataforma.'],
                    ['↗','Para las empresas','Brindar herramientas para publicar su oferta, administrar operaciones y conectar con nuevos pasajeros.'],
                ] as [$icon,$title,$description])
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 font-black text-emerald-700">{{$icon}}</span><h3 class="mt-4 font-extrabold text-slate-950">{{$title}}</h3><p class="mt-2 text-sm leading-6 text-slate-500">{{$description}}</p></article>
                @endforeach
            </section>

            <section class="mx-auto mt-8 flex max-w-6xl flex-col items-start justify-between gap-5 rounded-3xl bg-[#062c21] p-7 text-white sm:flex-row sm:items-center sm:p-9"><div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-emerald-300">Nuestro compromiso</p><h2 class="mt-2 text-xl font-extrabold sm:text-2xl">Una plataforma construida para la realidad amazónica</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-100/75">Trabajamos para que la información de cada salida sea útil, actualizada y accesible para pasajeros, operadores y comunidades.</p></div><a href="{{route('routes.index')}}" class="shrink-0 rounded-xl bg-amber-400 px-5 py-3 text-xs font-extrabold text-emerald-950 transition hover:bg-amber-300">Explorar rutas →</a></section>
        </div>
    </main>
</x-public-layout>
