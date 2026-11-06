@props(['label' => null, 'name', 'options' => [], 'value' => null])
<fieldset class="ny-ui-pills">
    @if($label)<legend>{{ $label }}</legend>@endif
    <div>
        @foreach($options as $option)
            <label>
                <input type="radio" name="{{ $name }}" value="{{ $option['value'] }}" @checked((string) $value === (string) $option['value'])>
                <span>{{ $option['label'] }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
