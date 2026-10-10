@props([
    'eyebrow',
    'title',
    'description',
    'initialTab' => 'resumen',
])

<div {{ $attributes->class(['w-full min-w-0']) }}>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex min-h-28 flex-col justify-between gap-5 border-b border-slate-200 px-5 py-5 lg:flex-row lg:items-center lg:px-6">
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[.18em] text-emerald-700">{{ $eyebrow }}</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">{{ $title }}</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">{{ $description }}</p>
            </div>
            @isset($actions)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>

        @isset($metrics)
            <div class="grid grid-cols-2 border-b border-slate-200 bg-slate-50/70 xl:grid-cols-4">{{ $metrics }}</div>
        @endisset

        @isset($navigation)
            <div class="border-b border-slate-200 px-4 py-3 lg:px-6">
                <nav class="flex min-h-11 gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1">{{ $navigation }}</nav>
            </div>
        @endisset

        <div class="min-h-[420px]">{{ $slot }}</div>
    </section>
</div>
