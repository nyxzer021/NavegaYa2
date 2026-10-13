@php
    try {
        $legalPolicies = \App\Models\PublicPage::query()
            ->whereIn('slug', ['terminos', 'privacidad', 'cookies', 'seguridad'])
            ->where('is_published', true)
            ->get(['slug', 'title', 'summary', 'content'])
            ->keyBy('slug')
            ->map(fn ($page) => [
                'title' => $page->title,
                'summary' => $page->summary,
                'content' => $page->content,
                'url' => route('pages.show', $page->slug),
            ]);
    } catch (\Throwable $exception) {
        $legalPolicies = collect();
    }
@endphp

<div
    x-data="{ open: false, policy: null, policies: @js($legalPolicies) }"
    @open-legal-policy.window="policy = policies[$event.detail.slug] || null; if (policy) { open = true; document.body.style.overflow = 'hidden' }"
    @keydown.escape.window="if (open) { open = false; document.body.style.overflow = '' }"
    x-effect="if (!open) document.body.style.overflow = ''"
    x-cloak
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[100] bg-slate-950/65 backdrop-blur-sm" aria-hidden="true"></div>
    <section x-show="open" x-transition class="fixed inset-0 z-[101] flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" :aria-label="policy?.title" @click.self="open=false; document.body.style.overflow=''">
        <article class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl border border-white/70 bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-5 border-b border-emerald-900/10 bg-gradient-to-r from-[#062c21] to-[#0d5b49] px-5 py-5 text-white sm:px-8">
                <div><p class="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-300">Información legal de NavegaYA</p><h2 class="mt-1 text-xl font-extrabold sm:text-2xl" x-text="policy?.title"></h2><p class="mt-1 max-w-2xl text-xs leading-relaxed text-emerald-100/80" x-text="policy?.summary"></p></div>
                <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-white/20 bg-white/10 text-xl transition hover:bg-white/20" @click="open=false; document.body.style.overflow=''" aria-label="Cerrar política">×</button>
            </header>
            <div class="overflow-y-auto px-5 py-6 sm:px-8 sm:py-7"><div class="whitespace-pre-line text-sm leading-7 text-slate-600" x-text="policy?.content"></div></div>
            <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-8"><a :href="policy?.url" class="text-xs font-bold text-emerald-800 hover:underline">Abrir en página completa</a><button type="button" class="rounded-xl bg-[#062c21] px-5 py-2.5 text-xs font-extrabold text-white hover:bg-[#0d5b49]" @click="open=false; document.body.style.overflow=''">Entendido</button></footer>
        </article>
    </section>
</div>
