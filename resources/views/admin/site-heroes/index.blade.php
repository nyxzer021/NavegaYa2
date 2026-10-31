<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6">
        @if(session('success'))<p class="rounded-xl bg-emerald-50 p-4 font-bold text-emerald-800">{{session('success')}}</p>@endif
        <header class="rounded-3xl bg-gradient-to-r from-[#073c31] to-[#176d60] p-7 text-white shadow-lg">
            <p class="text-xs font-black uppercase tracking-[.18em] text-emerald-200">Diseño del sitio público</p>
            <h1 class="mt-2 text-3xl font-black">Portadas de páginas públicas</h1>
            <p class="mt-2 max-w-3xl text-sm text-emerald-50/85">Todas se muestran con el mismo alto y proporción. Solo el superadministrador puede reemplazarlas.</p>
        </header>
        <div class="grid gap-5 md:grid-cols-2">
            @foreach($pages as $key => $label)
                @php($hero=$heroes->get($key))
                <form method="POST" action="{{route('admin.site-heroes.update',$key)}}" enctype="multipart/form-data" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    @csrf @method('PATCH')
                    <div class="aspect-[16/6] bg-slate-100">
                        <x-amazon-hero-bg :theme="$key" :image="$hero?->image_path ? Storage::url($hero->image_path) : null" class="!m-0 !h-full !min-h-0 !max-h-none !rounded-none !shadow-none" />
                    </div>
                    <div class="p-5">
                        <h2 class="text-lg font-black text-slate-950">{{$label}}</h2>
                        <p class="mt-1 text-xs text-slate-500">JPG, PNG o WebP · mínimo 1200 × 500 px · máximo 5 MB.</p>
                        <input required type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-4 block w-full rounded-xl border border-slate-200 p-2 text-xs">
                        @error('image')<p class="mt-2 text-xs font-bold text-rose-600">{{$message}}</p>@enderror
                        <button class="mt-4 rounded-xl bg-emerald-800 px-5 py-3 text-xs font-black text-white hover:bg-emerald-700">Reemplazar portada</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
</x-app-layout>
