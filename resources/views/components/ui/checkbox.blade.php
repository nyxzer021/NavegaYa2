@props(['label', 'name'])
<label class="ny-ui-check">
    <input type="checkbox" name="{{ $name }}" {{ $attributes }}>
    <span>{{ $label }}</span>
</label>
