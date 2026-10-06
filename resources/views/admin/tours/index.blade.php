@extends('layouts.admin')

@section('header')
    <h1 class="ny-page-title">Paquetes y Tours B2B</h1>
@endsection

@section('content')
    <div class="space-y-6">
        <section class="rounded-3xl bg-gradient-to-r from-[#062c21] to-emerald-800 p-7 text-white shadow-lg">
            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-300">Catálogo comercial de Loreto</span>
            <h2 class="mt-2 text-2xl font-black">Experiencias para pasajeros del marketplace</h2>
            <p class="mt-2 max-w-2xl text-sm text-emerald-100/80">Administra tours, atractivos y aliados turísticos que complementan la compra de pasajes.</p>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <a href="{{ route('admin.destination-listings.index', 'attraction') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-400 hover:shadow-md">
                <span class="text-2xl">🌿</span><h3 class="mt-3 font-black text-slate-900">Tours y atractivos</h3><p class="mt-1 text-xs leading-relaxed text-slate-500">Publica experiencias, excursiones y lugares turísticos.</p>
            </a>
            <a href="{{ route('admin.destination-listings.index', 'lodging') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-400 hover:shadow-md">
                <span class="text-2xl">🏨</span><h3 class="mt-3 font-black text-slate-900">Hospedajes aliados</h3><p class="mt-1 text-xs leading-relaxed text-slate-500">Gestiona hoteles, lodges y alojamientos comerciales.</p>
            </a>
            <a href="{{ route('admin.ads.index') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-400 hover:shadow-md">
                <span class="text-2xl">📣</span><h3 class="mt-3 font-black text-slate-900">Promoción B2B</h3><p class="mt-1 text-xs leading-relaxed text-slate-500">Destaca aliados mediante campañas y espacios patrocinados.</p>
            </a>
        </section>
    </div>
@endsection
