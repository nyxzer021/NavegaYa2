<x-public-layout :title="$city->name.' · NavegaYA'" current="discover" :image="$city->photoUrl()">
<div class="bg-slate-50">
<div class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
<x-amazon-hero-bg theme="discover" :image="$city->photoUrl()" class="flex items-end" overlay="from-slate-950/70 via-[#062c21]/40 to-slate-900/15">
<div class="max-w-3xl px-2 pb-8 pt-6">
<a href="{{ route('discover.index') }}" class="text-sm font-semibold text-emerald-100">← Todos los destinos</a>
<p class="mt-4 text-[11px] font-bold uppercase tracking-[.16em] text-amber-200">{{ $city->department }} · Río {{ $city->river }}</p>
<h1 class="mt-2 text-4xl font-black tracking-tight text-white">{{ $city->name }}</h1>
<p class="mt-2 max-w-xl text-sm leading-6 text-emerald-50">{{ $city->summary ?: 'Explora rutas, cultura y naturaleza amazónica.' }}</p>
</div>
</x-amazon-hero-bg>
<div class="mx-auto mt-8 max-w-6xl">
@if(session('success'))<p class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
<div class="grid gap-4 md:grid-cols-3">
<article class="rounded-3xl border border-slate-200 bg-white p-5"><h2 class="text-sm font-black uppercase tracking-wider text-emerald-800">Atractivos</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $city->attractions ?: 'Información próximamente.' }}</p></article>
<article class="rounded-3xl border border-slate-200 bg-white p-5"><h2 class="text-sm font-black uppercase tracking-wider text-emerald-800">Hospedajes</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $city->lodging ?: 'Información próximamente.' }}</p></article>
<article class="rounded-3xl border border-slate-200 bg-white p-5"><h2 class="text-sm font-black uppercase tracking-wider text-emerald-800">Gastronomía</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $city->gastronomy ?: 'Información próximamente.' }}</p></article>
</div>
<section class="mt-4 rounded-3xl border border-slate-200 bg-white p-5">
<h2 class="text-lg font-black text-slate-950">Rutas disponibles</h2>
@forelse($routes as $route)
<p class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-700"><strong>{{ $route->originPort->city }} → {{ $route->destinationPort->city }}</strong><br><span class="text-slate-500">{{ $route->originPort->name }} → {{ $route->destinationPort->name }}</span></p>
@empty
<p class="mt-3 text-sm text-slate-500">Aún no hay salidas publicadas para este destino.</p>
@endforelse
</section>
<section class="mt-4 rounded-3xl border border-slate-200 bg-white p-5">
<h2 class="text-lg font-black text-slate-950">Valoraciones de viajeros</h2>
<p class="mt-2 text-amber-500">{{ str_repeat('★', (int) round($average)) }}{{ str_repeat('☆', 5 - (int) round($average)) }} <strong class="text-slate-800">{{ $average ?: 'Sin valoraciones' }}</strong></p>
@forelse($reviews as $review)
<article class="mt-3 border-t border-slate-100 pt-3"><strong class="text-sm text-slate-900">{{ $review->user->name }}</strong><span class="text-amber-500"> {{ str_repeat('★', $review->rating) }}</span><p class="mt-1 text-sm text-slate-600">{{ $review->comment }}</p></article>
@empty
<p class="mt-3 text-sm text-slate-500">Aún no hay valoraciones verificadas.</p>
@endforelse
</section>
@auth
@if($canReview)
<section class="mt-4 rounded-3xl border border-slate-200 bg-white p-5">
<h2 class="text-lg font-black text-slate-950">Comparte tu experiencia</h2>
<p class="mt-1 text-sm text-slate-500">Tu valoración se publica después de una revisión.</p>
<form method="POST" action="{{ route('discover.review', $city) }}" class="mt-4 space-y-3">
@csrf
<label class="block text-sm font-semibold text-slate-700">Calificación
<select name="rating" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
<option value="5">5 · Excelente</option>
<option value="4">4 · Muy bueno</option>
<option value="3">3 · Bueno</option>
<option value="2">2 · Regular</option>
<option value="1">1 · Malo</option>
</select>
</label>
<textarea required minlength="10" name="comment" rows="4" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Cuéntanos sobre tu viaje"></textarea>
<button class="inline-flex h-11 items-center rounded-xl bg-emerald-800 px-4 text-sm font-bold text-white">Enviar valoración</button>
</form>
</section>
@endif
@else
<section class="mt-4 rounded-3xl border border-slate-200 bg-white p-5">
<h2 class="text-lg font-black text-slate-950">¿Ya viajaste?</h2>
<p class="mt-2 text-sm text-slate-600"><a href="{{ route('login') }}" class="font-bold text-emerald-800 underline">Ingresa a tu cuenta</a> para valorar este destino después de completar tu viaje.</p>
</section>
@endauth
</div>
</div>
</div>
</x-public-layout>
