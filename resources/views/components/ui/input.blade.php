@props(['label' => null, 'name'])
<label class="ny-ui-field">
    @if($label)<span>{{ $label }}</span>@endif
    <input name="{{ $name }}" {{ $attributes }}>
</label>
