@php
 $locale=app()->getLocale()==='en'?'en':'es';
 $supportNumber=preg_replace('/\D/','',\App\Models\SystemSetting::value('whatsapp_number','51900000000'));
 $supportLabel=\App\Models\SystemSetting::value('whatsapp_number','+51 900 000 000');
@endphp
<div class="w-full bg-[#08271f] text-emerald-50"><div class="mx-auto flex min-h-9 max-w-7xl items-center justify-between gap-3 px-4 py-1.5 text-[11px] sm:px-6 lg:px-8">
 <div class="hidden items-center gap-2 text-emerald-100 md:flex"><span>🌤️</span><span>📍 Iquitos <strong class="text-white">28°C</strong></span><span class="h-3 w-px bg-white/20"></span><span class="text-emerald-200">{{$locale==='en'?'River level: Normal':'Nivel del río: Normal'}}</span></div>
 <div class="ml-auto flex items-center divide-x divide-white/15"><a href="https://wa.me/{{$supportNumber}}" target="_blank" rel="noopener" class="flex items-center gap-1.5 px-2 text-emerald-100 transition hover:text-white md:px-3"><span>💬</span><span class="hidden sm:inline">{{$locale==='en'?'WhatsApp help':'Ayuda WhatsApp'}}: {{$supportLabel}}</span><span class="sm:hidden">WhatsApp</span></a><a href="{{route('company.registration')}}" class="hidden px-3 font-semibold text-amber-300 hover:text-amber-200 md:block">{{$locale==='en'?'Affiliate company':'Afiliar empresa'}}</a><div class="flex items-center pl-2"><a href="{{route('public.locale',['locale'=>'es'])}}" class="rounded-md px-2 py-1 font-bold {{$locale==='es'?'bg-white/15 text-white':'text-emerald-200'}}">ES</a><span class="text-white/25">|</span><a href="{{route('public.locale',['locale'=>'en'])}}" class="rounded-md px-2 py-1 font-bold {{$locale==='en'?'bg-white/15 text-white':'text-emerald-200'}}">EN</a></div></div>
</div></div>
