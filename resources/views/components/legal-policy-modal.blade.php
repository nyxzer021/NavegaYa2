@php
    try {
        $legalPolicies = \App\Models\PublicPage::query()
            ->whereIn('slug', ['terminos', 'privacidad', 'cookies', 'seguridad'])
            ->where('is_published', true)
            ->get(['slug', 'title', 'summary', 'content'])
            ->keyBy('slug');
    } catch (\Throwable $exception) {
        $legalPolicies = collect();
    }
@endphp

<div
    x-data="{ active: null }"
    @open-legal-policy.window="active = $event.detail.slug; document.body.style.overflow = 'hidden'"
    @keydown.escape.window="active = null; document.body.style.overflow = ''"
    x-effect="if (active === null) document.body.style.overflow = ''"
    x-cloak
>
    <div x-show="active !== null" x-transition.opacity class="fixed inset-0 z-[100] bg-slate-950/65 backdrop-blur-sm" aria-hidden="true"></div>
    <section x-show="active !== null" x-transition class="fixed inset-0 z-[101] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Información legal" @click.self="active=null; document.body.style.overflow=''">
        <article class="flex max-h-[92dvh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl sm:rounded-3xl">
            @forelse($legalPolicies as $slug => $policy)
                <div x-show="active === '{{$slug}}'" class="min-h-0 flex-1 flex-col" :class="active === '{{$slug}}' ? 'flex' : 'hidden'">
                    <header class="flex shrink-0 items-start justify-between gap-4 border-b border-emerald-900/10 bg-gradient-to-r from-[#062c21] to-[#0d5b49] px-5 py-4 text-white sm:px-8 sm:py-5">
                        <div class="min-w-0"><p class="text-[9px] font-extrabold uppercase tracking-[.18em] text-emerald-300 sm:text-[10px]">Información legal de NavegaYA</p><h2 class="mt-1 text-lg font-extrabold leading-tight sm:text-2xl">{{$policy->title}}</h2>@if($policy->summary)<p class="mt-1 hidden max-w-2xl text-xs leading-relaxed text-emerald-100/80 sm:block">{{$policy->summary}}</p>@endif</div>
                        <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-white/25 bg-white/10 text-2xl leading-none transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-amber-300" @click="active=null; document.body.style.overflow=''" aria-label="Cerrar">×</button>
                    </header>
                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 sm:px-8 sm:py-7"><div class="whitespace-pre-line text-sm leading-7 text-slate-600">{{$policy->content}}</div></div>
                    <footer class="flex shrink-0 justify-end border-t border-slate-200 bg-slate-50 px-5 py-3 sm:px-8 sm:py-4"><button type="button" class="rounded-xl bg-[#062c21] px-6 py-2.5 text-xs font-extrabold text-white transition hover:bg-[#0d5b49] focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2" @click="active=null; document.body.style.overflow=''">Cerrar</button></footer>
                </div>
            @empty
                <div class="flex min-h-64 flex-col">
                    <header class="flex items-center justify-between bg-[#062c21] px-5 py-4 text-white"><h2 class="font-extrabold">Información legal de NavegaYA</h2><button type="button" class="grid h-10 w-10 place-items-center rounded-full border border-white/25 text-2xl" @click="active=null; document.body.style.overflow=''" aria-label="Cerrar">×</button></header>
                    <div class="grid flex-1 place-items-center p-8 text-center text-sm text-slate-500">La información legal se está actualizando. Inténtalo nuevamente en unos minutos.</div>
                    <footer class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3"><button type="button" class="rounded-xl bg-[#062c21] px-6 py-2.5 text-xs font-extrabold text-white" @click="active=null; document.body.style.overflow=''">Cerrar</button></footer>
                </div>
            @endforelse
        </article>
    </section>
</div>
