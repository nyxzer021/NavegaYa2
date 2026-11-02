@props(['name','label','type'=>'text','placeholder'=>'','required'=>false])
<label {{ $attributes->merge(['class' => 'ny-company-field']) }}><span>{{ $label }}</span><input type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}" placeholder="{{ $placeholder }}" @required($required)></label>
