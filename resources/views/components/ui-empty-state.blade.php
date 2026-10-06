@props(['title' => 'Aún no hay información', 'description' => null])
<section {{ $attributes->merge(['class' => 'ny-empty']) }}>
    <strong>{{ $title }}</strong>
    @if($description)<p>{{ $description }}</p>@endif
    @if(isset($action))<div style="margin-top:14px">{{ $action }}</div>@endif
</section>
