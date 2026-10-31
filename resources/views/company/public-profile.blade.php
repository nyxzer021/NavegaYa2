<x-app-layout>
<x-slot name="header"><h2 class="ny-page-title">Perfil público de empresa</h2></x-slot>
<div class="mx-auto max-w-4xl">
 @if(session('success'))<p class="mb-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{session('success')}}</p>@endif
 <form method="POST" enctype="multipart/form-data" action="{{route('company.public-profile.update')}}" class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-slate-200">@csrf @method('PATCH')
  <p class="text-xs font-bold uppercase tracking-widest text-[#0d6b5e]">Visible para viajeros</p><h1 class="mt-2 text-2xl font-bold">{{$organization->commercial_name ?: $organization->legal_name}}</h1>
  <div class="mt-6 grid gap-5 md:grid-cols-2">
   <label>Nombre comercial<input name="commercial_name" value="{{old('commercial_name',$organization->commercial_name)}}"></label>
   <label>WhatsApp de boletería<input name="whatsapp" value="{{old('whatsapp',$organization->whatsapp)}}" placeholder="999 999 999"></label>
   <label>Teléfono de central<input name="phone" value="{{old('phone',$organization->phone)}}"></label>
   <label>Horario de atención<input name="service_hours" value="{{old('service_hours',$organization->service_hours)}}" placeholder="Lun–Sáb 08:00–18:00"></label>
   <label class="md:col-span-2">Mensaje inicial de WhatsApp<input name="whatsapp_message" value="{{old('whatsapp_message',$organization->whatsapp_message)}}" placeholder="Hola, deseo información sobre sus salidas"></label>
   <label class="flex items-center gap-3 rounded-xl bg-emerald-50 p-3 text-sm md:col-span-2"><input type="checkbox" name="show_public_whatsapp" value="1" @checked(old('show_public_whatsapp',$organization->show_public_whatsapp))> Mostrar WhatsApp a los clientes</label>
   <label>Sitio web<input type="url" name="website" value="{{old('website',$organization->website)}}"></label>
   <label>Dirección<input name="address" value="{{old('address',$organization->address)}}"></label>
  </div>
  <label class="mt-5 block">Descripción de la empresa<textarea name="public_description" rows="4">{{old('public_description',$organization->public_description)}}</textarea></label>
  <div class="mt-6 grid gap-5 md:grid-cols-2"><label>Logo<input type="file" accept="image/*" name="logo"></label><label>Imagen de portada<input type="file" accept="image/*" name="cover"></label></div>
  @if($errors->any())<p class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">{{$errors->first()}}</p>@endif
  <button class="mt-7 rounded-xl bg-emerald-800 px-5 py-3 font-bold text-white">Guardar perfil público</button>
 </form>
</div>
</x-app-layout>
