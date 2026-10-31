@php
 $localizedTitle=$page->localized('title');
 $localizedSummary=$page->localized('summary');
 $localizedContent=$page->localized('content');
@endphp
<x-public-layout :title="$localizedTitle.' · NavegaYA'" :description="$localizedSummary ?: \Illuminate\Support\Str::limit(strip_tags($localizedContent),155)">
<main class="bg-slate-50 px-4 py-10"><article class="mx-auto max-w-4xl overflow-hidden rounded-2xl border bg-white shadow-sm">
<header class="border-b px-6 py-8 sm:px-10"><p class="mb-2 text-xs font-bold uppercase text-emerald-700">{{app()->getLocale()==='en'?'NavegaYA legal information':'Información legal de NavegaYA'}}</p>@if($page->isSpanishFallback())<p class="mb-3 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">{{__('public.spanish_only')}}</p>@endif<h1 class="text-3xl font-extrabold">{{$localizedTitle}}</h1>@if($localizedSummary)<p class="mt-3 text-sm text-slate-600">{{$localizedSummary}}</p>@endif</header>
<div class="whitespace-pre-line px-6 py-8 text-sm leading-7 text-slate-700 sm:px-10">{{$localizedContent}}</div>
</article><a href="{{route('home')}}" class="mx-auto mt-5 block max-w-4xl font-bold text-emerald-800">{{app()->getLocale()==='en'?'← Back to NavegaYA':'← Volver a NavegaYA'}}</a></main>
</x-public-layout>
