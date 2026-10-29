@props([
    'eyebrow',
    'title',
    'description',
    'initialTab' => 'resumen',
    'showHeader' => true,
    'headerTone' => 'light',
])
@php
    $darkHeader = $headerTone === 'green';
@endphp

<div {{ $attributes->class(['w-full min-w-0']) }}>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @if($showHeader)
        <header @class([
            'flex min-h-28 flex-col justify-between gap-5 border-b px-5 py-5 lg:flex-row lg:items-center lg:px-6',
            'border-emerald-900 bg-gradient-to-r from-[#062c21] to-[#176d60] text-white' => $darkHeader,
            'border-slate-200 bg-white' => ! $darkHeader,
        ])>
            <div class="min-w-0">
                <p @class(['text-[10px] font-black uppercase tracking-[.18em]', 'text-emerald-300' => $darkHeader, 'text-emerald-700' => ! $darkHeader])>{{ $eyebrow }}</p>
                <h2 @class(['mt-1 text-2xl font-black tracking-tight', 'text-white' => $darkHeader, 'text-slate-950' => ! $darkHeader])>{{ $title }}</h2>
                <p @class(['mt-1 max-w-3xl text-sm', 'text-emerald-100/75' => $darkHeader, 'text-slate-500' => ! $darkHeader])>{{ $description }}</p>
            </div>
            @isset($actions)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
        @endif

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
