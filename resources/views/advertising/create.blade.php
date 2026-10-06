<x-public-layout title="Anuncia tu negocio en Loreto · NavegaYA">
<main class="min-h-screen bg-slate-50 py-8 sm:py-12">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 lg:grid-cols-[.9fr_1.1fr] lg:px-8">
        <section class="rounded-3xl bg-[#062c21] p-7 text-white shadow-xl sm:p-10">
            <span class="inline-flex rounded-full border border-amber-300/30 bg-amber-400/10 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-amber-300">Publicidad turística B2B</span>
            <h1 class="mt-5 text-3xl font-black leading-tight sm:text-4xl">Conecta tu negocio con viajeros que ya están comprando su pasaje.</h1>
            <p class="mt-4 text-sm leading-relaxed text-emerald-100/80">Promociona tu lodge, restaurante, tour o servicio chárter en los momentos clave de planificación del viaje.</p>
            <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                @foreach(['🏠 Portada de NavegaYA','🧭 Catálogo de rutas','🚤 Perfiles de operadores','🎫 Boleto digital'] as $benefit)
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-xs font-bold">{{ $benefit }}</div>
                @endforeach
            </div>
            <p class="mt-7 text-[11px] leading-relaxed text-emerald-100/60">La solicitud no publica automáticamente ningún anuncio. El equipo comercial valida el negocio, acuerda la pauta y activa la campaña.</p>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Solicitud comercial</p>
            <h2 class="mt-1 text-2xl font-black text-slate-900">Cuéntanos sobre tu negocio</h2>
            @if(session('success'))<div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">✓ {{ session('success') }}</div>@endif
            @if($errors->any())<div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800"><strong>Revisa los datos ingresados.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('advertising.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
                <label class="text-xs font-bold text-slate-700 sm:col-span-2">Nombre comercial<input name="business_name" value="{{ old('business_name') }}" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm" placeholder="Ej. Lodge Río Amazonas"></label>
                <label class="text-xs font-bold text-slate-700">Tipo de negocio<select name="business_type" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm"><option value="">Selecciona</option>@foreach($businessTypes as $value => $label)<option value="{{ $value }}" @selected(old('business_type') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-xs font-bold text-slate-700">Ciudad / destino<input name="city_destination" value="{{ old('city_destination', 'Iquitos') }}" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm"></label>
                <label class="text-xs font-bold text-slate-700">Persona de contacto<input name="contact_name" value="{{ old('contact_name') }}" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm"></label>
                <label class="text-xs font-bold text-slate-700">WhatsApp<input name="phone_whatsapp" value="{{ old('phone_whatsapp') }}" required class="mt-1.5 w-full rounded-xl border-slate-200 text-sm" placeholder="+51 999 999 999"></label>
                <label class="text-xs font-bold text-slate-700">Correo<input type="email" name="email" value="{{ old('email') }}" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm"></label>
                <label class="text-xs font-bold text-slate-700">RUC (opcional)<input name="ruc" value="{{ old('ruc') }}" inputmode="numeric" maxlength="11" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm"></label>
                <fieldset class="sm:col-span-2"><legend class="text-xs font-bold text-slate-700">¿Dónde deseas aparecer?</legend><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach($placements as $value => $label)<label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-semibold text-slate-700"><input type="checkbox" name="placements[]" value="{{ $value }}" @checked(in_array($value, old('placements', []))) class="rounded border-slate-300 text-emerald-700">{{ $label }}</label>@endforeach</div></fieldset>
                <label class="text-xs font-bold text-slate-700 sm:col-span-2">Sitio web o WhatsApp de destino<input type="url" name="target_url" value="{{ old('target_url') }}" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm" placeholder="https://wa.me/51..."></label>
                <label class="text-xs font-bold text-slate-700 sm:col-span-2">Banner o fotografía (máx. 4 MB)<input type="file" name="banner_image" accept="image/*" class="mt-1.5 block w-full rounded-xl border border-slate-200 p-3 text-xs"></label>
                <button class="sm:col-span-2 rounded-xl bg-amber-500 px-5 py-3.5 text-sm font-black text-slate-950 transition hover:bg-amber-600">Solicitar propuesta comercial →</button>
            </form>
        </section>
    </div>
</main>
</x-public-layout>
