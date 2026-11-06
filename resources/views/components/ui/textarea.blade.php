@props(['label' => null, 'name'])
<label class="ny-ui-field">
    @if($label)<span>{{ $label }}</span>@endif
    <textarea name="{{ $name }}" {{ $attributes }}></textarea>
</label>
