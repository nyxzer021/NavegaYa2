@php($v=fn($key,$default='')=>old($key,$page?->{$key}??$default))
<div class="grid gap-4 md:grid-cols-2"><label class="field">Título en español<input name="title" required value="{{$v('title')}}"></label><label class="field">Title in English<input name="title_en" value="{{$v('title_en')}}"></label></div>
<label class="field">Dirección corta (slug)<input name="slug" value="{{$v('slug')}}" placeholder="se genera automáticamente"></label>
<div class="grid gap-4 md:grid-cols-2"><label class="field">Resumen en español<textarea name="summary" rows="3">{{$v('summary')}}</textarea></label><label class="field">English summary<textarea name="summary_en" rows="3">{{$v('summary_en')}}</textarea></label></div>
<div class="grid gap-4 md:grid-cols-2"><label class="field">Contenido en español<textarea name="content" rows="10">{{$v('content')}}</textarea></label><label class="field">English content<textarea name="content_en" rows="10">{{$v('content_en')}}</textarea></label></div>
<p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">Si la versión inglesa queda vacía, la página mostrará el contenido completo en español con el aviso “Available in Spanish only”.</p>
<div class="checks"><label><input type="hidden" name="is_published" value="0"><input type="checkbox" name="is_published" value="1" @checked($v('is_published',true))> Publicada</label></div>
