@props(['label' => null, 'name', 'hint' => null])
<label class="ny-ui-drop">
    @if($label)<span>{{ $label }}</span>@endif
    <input type="file" name="{{ $name }}" {{ $attributes }}>
    @if($hint)<small>{{ $hint }}</small>@endif
</label>
