<x-app-layout>
    <x-slot name="header"><h2 class="ny-page-title">Configuración general</h2></x-slot>
    <div class="mx-auto max-w-4xl space-y-6">
        @if(session('success'))<p class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{session('success')}}</p>@endif
        <section class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-bold uppercase tracking-widest text-[#0d6b5e]">Atención al viajero</p><h1 class="mt-2 text-2xl font-bold">Botón de WhatsApp</h1><p class="mt-2 text-slate-600">Este número se usa en los botones públicos de ayuda.</p>
            <form method="POST" action="{{route('admin.settings.whatsapp')}}" class="mt-6 space-y-5">@csrf @method('PATCH')
                <label class="block font-semibold">Número oficial de WhatsApp<input required name="whatsapp_number" inputmode="numeric" value="{{old('whatsapp_number',$whatsappNumber)}}" class="mt-1 w-full rounded-lg border-slate-300"/><span class="mt-1 block text-sm font-normal text-slate-500">Código de país sin +, espacios ni guiones. Ejemplo: 51999999999.</span></label>
                <label class="block font-semibold">Mensaje predeterminado<textarea required name="whatsapp_message" rows="4" class="mt-1 w-full rounded-lg border-slate-300">{{old('whatsapp_message',$whatsappMessage)}}</textarea></label>
                <button style="border:0;border-radius:8px;padding:12px 20px;background:#0d6b5e;color:#fff;font-weight:700;cursor:pointer">Guardar WhatsApp</button>
            </form>
        </section>
        <section class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-bold uppercase tracking-widest text-[#0d6b5e]">Encabezado y pie de página</p><h2 class="mt-2 text-2xl font-bold">Identidad de la página pública</h2><p class="mt-2 text-slate-600">Cambia el nombre visible del portal y los textos del pie de página.</p>
            <form method="POST" action="{{route('admin.settings.chrome')}}" class="mt-6 space-y-5">@csrf @method('PATCH')
                <label class="block font-semibold">Nombre en el encabezado<input required name="site_brand" value="{{old('site_brand',$siteBrand)}}" class="mt-1 w-full rounded-lg border-slate-300"></label>
                <label class="block font-semibold">Descripción del pie de página<textarea required rows="3" name="footer_description" class="mt-1 w-full rounded-lg border-slate-300">{{old('footer_description',$footerDescription)}}</textarea></label>
                <label class="block font-semibold">Texto de derechos<input required name="footer_rights" value="{{old('footer_rights',$footerRights)}}" class="mt-1 w-full rounded-lg border-slate-300"></label>
                <button style="border:0;border-radius:8px;padding:12px 20px;background:#0d6b5e;color:#fff;font-weight:700;cursor:pointer">Guardar encabezado y pie</button>
            </form>
        </section>
        <section class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-bold uppercase tracking-widest text-[#0d6b5e]">Pagos y comisiones</p><h2 class="mt-2 text-2xl font-bold">Comisión NavegaYA</h2><p class="mt-2 text-slate-600">Porcentaje de comisión retenido por NavegaYA sobre cada boleto comercializado a través de la pasarela web. Las empresas reciben el valor neto restante en su liquidación periódica.</p><form method="POST" action="{{route('admin.settings.payments')}}" class="mt-6 flex flex-wrap items-end gap-4">@csrf @method('PATCH')<label class="block font-semibold">Porcentaje de comisión<input required type="number" min="0" max="30" step="0.01" name="platform_fee_percent" value="{{old('platform_fee_percent',$platformFeePercent)}}" class="mt-1 block w-52 rounded-lg border-slate-300"><span class="mt-1 block text-sm font-normal text-slate-500">Ejemplo: 10 significa 10%.</span></label><button style="border:0;border-radius:8px;padding:12px 20px;background:#0d6b5e;color:#fff;font-weight:700;cursor:pointer">Guardar comisión</button></form></section>        <section class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200"><p class="text-xs font-bold uppercase tracking-widest text-[#0d6b5e]">Portada e imágenes de fondo</p><h2 class="mt-2 text-2xl font-bold">Portada principal</h2><p class="mt-2 text-slate-600">Administra las imágenes, textos, botones, orden y duración de la portada pública.</p><a href="{{route('admin.home-hero.index')}}" style="display:inline-block;margin-top:20px;border-radius:8px;padding:12px 20px;background:#0d6b5e;color:#fff;font-weight:700;text-decoration:none">Administrar portada e imágenes →</a></section>
        <section class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-bold uppercase tracking-widest text-[#0d6b5e]">Diseño público</p><h2 class="mt-2 text-2xl font-bold">Estilo de NavegaYA</h2><p class="mt-2 text-slate-600">Controla colores, tipografía y altura uniforme de los banners públicos.</p>
            <form method="POST" action="{{route('admin.settings.theme')}}" class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2">@csrf @method('PATCH')
                <label class="block font-semibold">Estilo de títulos<select name="theme_font" class="mt-1 w-full rounded-lg border-slate-300"><option value="elegant" @selected($themeFont==='elegant')>Elegante amazónico</option><option value="modern" @selected($themeFont==='modern')>Moderno y limpio</option></select></label>
                <label class="block font-semibold">Altura de banner (px)<input required type="number" min="280" max="520" name="theme_hero_height" value="{{old('theme_hero_height',$themeHeroHeight)}}" class="mt-1 w-full rounded-lg border-slate-300"></label>
                <label class="block font-semibold">Verde principal<input required type="color" name="theme_forest" value="{{old('theme_forest',$themeForest)}}" class="mt-1 h-11 w-full rounded-lg border-slate-300"></label>
                <label class="block font-semibold">Dorado de botones<input required type="color" name="theme_gold" value="{{old('theme_gold',$themeGold)}}" class="mt-1 h-11 w-full rounded-lg border-slate-300"></label>
                <label class="block font-semibold">Color de río<input required type="color" name="theme_river" value="{{old('theme_river',$themeRiver)}}" class="mt-1 h-11 w-full rounded-lg border-slate-300"></label>
                <div class="flex items-end"><button style="border:0;border-radius:8px;padding:12px 20px;background:#0d6b5e;color:#fff;font-weight:700;cursor:pointer">Guardar diseño público</button></div>
            </form>
        </section>    </div>
</x-app-layout>
