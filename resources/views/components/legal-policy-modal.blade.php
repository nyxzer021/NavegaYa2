@php
    try {
        $legalPolicies = \App\Models\PublicPage::query()
            ->whereIn('slug', ['terminos', 'privacidad', 'cookies', 'seguridad'])
            ->where('is_published', true)
            ->get(['slug', 'title', 'summary', 'content']);
    } catch (\Throwable $exception) {
        $legalPolicies = collect();
    }
@endphp

@foreach($legalPolicies as $policy)
    <div
        x-data="{ open: false }"
        @open-legal-policy.window="open = ($event.detail.slug === '{{$policy->slug}}')"
        @keydown.escape.window="open = false"
        x-cloak
    >
        <div x-show="open" x-transition.opacity class="fixed inset-0 z-[100] bg-slate-950/65 backdrop-blur-sm" aria-hidden="true"></div>
        <section x-show="open" x-transition class="fixed inset-0 z-[101] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="legal-title-{{$policy->slug}}" @click.self="open=false">
            <article class="flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl sm:rounded-3xl">
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-emerald-900/10 bg-gradient-to-r from-[#062c21] to-[#0d5b49] px-5 py-4 text-white sm:px-8 sm:py-5">
                    <div class="min-w-0"><p class="text-[9px] font-extrabold uppercase tracking-[.18em] text-emerald-300 sm:text-[10px]">Información legal de NavegaYA</p><h2 id="legal-title-{{$policy->slug}}" class="mt-1 text-lg font-extrabold leading-tight sm:text-2xl">{{$policy->title}}</h2>@if($policy->summary)<p class="mt-1 hidden max-w-2xl text-xs leading-relaxed text-emerald-100/80 sm:block">{{$policy->summary}}</p>@endif</div>
                    <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-white/25 bg-white/10 text-2xl leading-none transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-amber-300" @click="open=false" aria-label="Cerrar">×</button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 sm:px-8 sm:py-7"><div class="whitespace-pre-line text-sm leading-7 text-slate-600">{{$policy->content}}</div></div>
                <footer class="flex shrink-0 justify-end border-t border-slate-200 bg-slate-50 px-5 py-3 sm:px-8 sm:py-4"><button type="button" class="rounded-xl bg-[#062c21] px-6 py-2.5 text-xs font-extrabold text-white transition hover:bg-[#0d5b49] focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2" @click="open=false">Cerrar</button></footer>
            </article>
        </section>
    </div>
@endforeach
