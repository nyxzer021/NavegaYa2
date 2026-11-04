@props(['label' => null, 'name'])
<label class="ny-ui-field">
    @if($label)<span>{{ $label }}</span>@endif
    <select name="{{ $name }}" {{ $attributes }}>{{ $slot }}</select>
</label>
